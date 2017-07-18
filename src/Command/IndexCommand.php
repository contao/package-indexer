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
use AlgoliaSearch\Index;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class IndexCommand extends Command
{
    /**
     * @var GuzzleClient
     */
    private $client;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('index')
            ->setDescription('Starts the indexing process')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packages = array_unique(array_merge(
            $this->getPackageNames('contao-bundle'),
            $this->getPackageNames('contao-module')
        ));

        $this->io = new SymfonyStyle($input, $output);
        $this->io->newLine();

        $this->indexPackages($packages);
        $this->indexMetapackages($packages);
    }

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

    private function indexMetapackages(array $packages)
    {
        $names = $this->getPackageNames('metapackage');

        $this->io->writeln('Parsing metapackages: ');
        $progressBar = $this->io->createProgressBar(count($names));

        $this->indexRequirements($progressBar, $names, $packages);

        $progressBar->finish();
        $this->io->newLine(2);
    }

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

            $version = reset($package['versions']);

            if (!isset($version['require'])) {
                continue;
            }

            if (count(array_intersect(array_keys($version['require']), $required)) > 0) {
                $objects[$name] = $this->extractSearchData($package);
                $required[] = $name;
            } elseif (count($sub = array_intersect(array_keys($version['require']), $names)) > 0
                && !in_array($name, $sub)
            ) {
                $children[] = $name;
            }

            $progressBar->advance();
        }

        $this->index($objects);

        if (0 !== count($children)) {
            $this->indexRequirements($progressBar, $children, $required);
        }
    }

    private function getPackageNames(string $type): array
    {
        $data = $this->getJson('https://packagist.org/packages/list.json?type='.$type, $cacheHit);

        return $data['packageNames'];
    }

    private function getPackage(string $name): ?array
    {
        $data = $this->getJson('https://packagist.org/packages/'.$name.'.json', $packageCache);
        $versions = $this->getJson('https://packagist.org/p/'.$name.'.json', $composerCache);

        if ($packageCache && $composerCache) {
            $this->io->writeln(' – Cache HIT for ' . $name, SymfonyStyle::VERBOSITY_DEBUG);
            return null;
        }

        $this->io->writeln(' – Cache MISS for ' . $name, SymfonyStyle::VERBOSITY_DEBUG);

        $package = $data['package'];
        $package['versions'] = $versions['packages'][$name];

        return $package;
    }

    private function extractSearchData(array $package): array
    {
        $supported = false;
        $latest = end($package['versions']);

        foreach ($package['versions'] as $version) {
            if (isset($version['require']['contao/core-bundle'])) {
                $supported = true;
                break;
            }
        }

        $data = [
            'name' => $package['name'],
            'description' => $latest['description'],
            'keywords' => $latest['keywords'],
            'homepage' => $latest['homepage'] ?? ($package['repository'] ?? 'https://packagist.org/packages/'.$package['name']),
            'links' => $latest['support'] ?? [],
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

    private function index(array $objects): void
    {
        if (0 === count($objects)) {
            return;
        }

        if (null === $this->index) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $client = new AlgoliaClient(@getenv('ALGOLIA_APP', true), @getenv('ALGOLIA_KEY', true));
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $this->index = $client->initIndex(@getenv('ALGOLIA_INDEX', true));
        }

        $this->io->newLine();
        $this->io->writeln('Indexing '.count($objects).' objects …');

        $this->index->saveObjects($objects, 'name');
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
