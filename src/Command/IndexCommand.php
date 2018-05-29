<?php

/*
 * This file is part of Contao Package Indexer.
 *
 * Copyright (c) 2017 Contao Association
 *
 * @license LGPL-3.0+
 */

namespace Contao\PackageIndexer\Command;

use AlgoliaSearch\Client as AlgoliaClient;
use Contao\PackageIndexer\Algolia\IndexInterface;
use Contao\PackageIndexer\Algolia\V1Index;
use Contao\PackageIndexer\Algolia\V2Index;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class IndexCommand extends Command
{
    /**
     * Languages for the search index
     * @see https://github.com/contao/contao-manager/blob/master/src/i18n/locales.js
     */
    private const LANGUAGES = ['en', 'de', 'br', 'cs', 'es', 'fa', 'fr', 'ja', 'lv', 'nl', 'pl', 'ru', 'sr', 'zh'];

    /**
     * Blacklisted packages that should not be indexed
     */
    private const BLACKLIST = ['contao/installation-bundle', 'contao/module-devtools', 'contao/module-repository'];

    /**
     * @var GuzzleClient
     */
    private $client;

    /**
     * @var IndexInterface[]
     */
    private $indexes;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var array
     */
    private $uncached = [];

    /**
     * @var bool
     */
    private $clearAll = false;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('index')
            ->setDescription('Starts the indexing process')
            ->addOption('uncached', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY)
            ->addOption('clear-all', null, InputOption::VALUE_NONE, '', false)
        ;
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->uncached = $input->getOption('uncached');
        $this->clearAll = (bool) $input->getOption('clear-all');

        $packages = array_diff(
            array_unique(
                array_merge(
                    $this->getPackageNames('contao-bundle'),
                    $this->getPackageNames('contao-module')
                )
            ),
            self::BLACKLIST
        );

        $this->io = new SymfonyStyle($input, $output);
        $this->io->newLine();

        $this->indexPackages($packages);
        $this->indexMetapackages($packages);
    }

    /**
     * @param array $names
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function indexPackages(array $names): void
    {
        $this->io->writeln('Parsing Contao packages: ');
        $progressBar = $this->io->createProgressBar(count($names));
        $progressBar->start();

        foreach (array_chunk($names, 100) as $chunk) {
            $objects = [];

            foreach ($chunk as $name) {
                $package = $this->getPackage($name);

                if (null !== $package) {
                    $objects[] = $this->extractSearchData($package);
                }

                $progressBar->advance();
            }

            $this->index($objects);
            unset($objects);
        }

        $progressBar->finish();
        $this->io->newLine(2);
    }

    /**
     * @param array $packages
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function indexMetapackages(array $packages)
    {
        $names = $this->getPackageNames('metapackage');

        $this->io->writeln('Parsing metapackages: ');
        $progressBar = $this->io->createProgressBar(count($names));

        $this->indexRequirements($progressBar, $names, $packages);

        $progressBar->finish();
        $this->io->newLine(2);
    }

    /**
     * @param ProgressBar $progressBar
     * @param array       $names
     * @param array       $required
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function indexRequirements(ProgressBar $progressBar, array $names, array $required): void
    {
        $max = $progressBar->getMaxSteps();
        $progressBar->start($max + count($names));
        $progressBar->setProgress($max);

        $objects = [];
        $children = [];

        foreach ($names as $name) {
            $package = $this->getPackage($name);

            if (null === $package) {
                $progressBar->advance();
                continue;
            }

            $supported = null;

            foreach ($package['versions'] as $version) {
                if (!isset($version['require'])) {
                    continue;
                }

                if (!empty($requires = array_intersect(array_keys($version['require']), $required))) {
                    foreach ($requires as $require) {
                        $supported = false;

                        if ($this->extractSearchData($this->getPackage($require, false))['supported']) {
                            $supported = true;
                            break(2);
                        }
                    }
                } elseif (count($sub = array_intersect(array_keys($version['require']), $names)) > 0
                    && !in_array($name, $sub)
                ) {
                    $children[] = $name;
                }
            }

            if (true === $supported || false === $supported) {
                $objects[$name] = $this->extractSearchData($package, $supported);
                $required[] = $name;
            }

            $progressBar->advance();
        }

        $this->index($objects);

        if (0 !== count($children)) {
            $this->indexRequirements($progressBar, $children, $required);
        }
    }

    /**
     * @param string $type
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPackageNames(string $type): array
    {
        $data = $this->getJson('https://packagist.org/packages/list.json?type='.$type, $cacheHit);

        return $data['packageNames'];
    }

    /**
     * @param string $name
     * @param bool   $cache
     *
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPackage(string $name, $cache = true): ?array
    {
        try {
            $data = $this->getJson('https://packagist.org/packages/' . $name . '.json', $packageCache);
            $versions = $this->getJson('https://packagist.org/p/' . $name . '.json', $composerCache);
        } catch (\Exception $e) {
            $this->io->writeln(' - ERROR fetching package '.$name, SymfonyStyle::VERBOSITY_NORMAL);
            return null;
        }

        if ($cache && $packageCache && $composerCache && !$this->clearAll && !in_array($name, $this->uncached)) {
            $this->io->writeln(' – Cache HIT for '.$name, SymfonyStyle::VERBOSITY_DEBUG);
            return null;
        }

        $this->io->writeln(' – Cache MISS for '.$name, SymfonyStyle::VERBOSITY_DEBUG);

        $package = $data['package'];
        $package['versions'] = $versions['packages'][$name];

        return $package;
    }

    private function extractSearchData(array $package, $supported = false): array
    {
        $latest = end($package['versions']);

        if (!$supported) {
            foreach ($package['versions'] as $version) {
                if (isset($version['require']['contao/core-bundle'])) {
                    $supported = true;
                    break;
                }
            }
        }

        $data = [
            'name' => $package['name'],
            'title' => $package['name'],
            'description' => $latest['description'],
            'keywords' => $latest['keywords'],
            'homepage' => $latest['homepage'] ?? '',
            'support' => $latest['support'] ?? [],
            'license' => $latest['license'] ?? '',
            'downloads' => $package['downloads']['total'] ?? 0,
            'stars' => $package['favers'] ?? 0,
            'supported' => $supported,
            'managed' => $latest['type'] !== 'contao-bundle' || isset($latest['extra']['contao-manager-plugin']),
            'abandoned' => isset($package['abandoned']),
            'replacement' => $package['abandoned'] ?? '',
        ];

        return $data;
    }

    /**
     * @param array $objects
     *
     * @throws \Exception
     */
    private function index(array $objects): void
    {
        if (0 === count($objects)) {
            return;
        }

        if (null === $this->indexes) {
            $client = new AlgoliaClient(@getenv('ALGOLIA_APP', true), @getenv('ALGOLIA_KEY', true));
            $this->indexes = [new V1Index($client, $this->clearAll)];

            foreach (self::LANGUAGES as $language) {
                $this->indexes[$language] = new V2Index($client, $language, null, $this->clearAll);
            }
        }

        $this->io->newLine();
        $this->io->writeln('Indexing '.count($objects).' objects …');

        foreach ($this->indexes as $index) {
            $index->push($objects);
        }
    }

    private function http()
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $cacheDir = __DIR__.'/../../cache';

        (new Filesystem())->mkdir($cacheDir);

        $stack = HandlerStack::create();
        $storage = new Psr6CacheStorage(new FilesystemAdapter('', 0, $cacheDir));
        $stack->push(new CacheMiddleware(new PublicCacheStrategy($storage)), 'cache');

        $this->client = new GuzzleClient(['handler' => $stack]);

        return $this->client;
    }

    /**
     * @param $uri
     * @param $cacheHit
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getJson($uri, &$cacheHit)
    {
        $response = $this->http()->request('GET', $uri);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Response error. Status code %s', $response->getStatusCode()));
        }

        $cacheHit = $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO) === CacheMiddleware::HEADER_CACHE_HIT;

        return json_decode($response->getBody(), true);
    }
}
