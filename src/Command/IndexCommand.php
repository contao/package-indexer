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

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        $this->indexPackages($io, $packages);
        $this->indexMetapackages($io, $packages);

        $io->newLine();
    }

    private function indexPackages(SymfonyStyle $io, array $names): void
    {
        $io->writeln('Indexing Contao packages: ');
        $progressBar = $io->createProgressBar(count($names));
        $progressBar->start();

        foreach (array_chunk($names, 100) as $chunk) {
            $objects = [];

            foreach ($chunk as $name) {
                $objects[] = $this->extractSearchData($this->getPackage($name));
                $progressBar->advance();
            }

            $this->index($objects);
            unset($objects);
        }

        $progressBar->finish();
        $io->newLine();
    }

    private function indexMetapackages(SymfonyStyle $io, array $packages)
    {
        $names = $this->getPackageNames('metapackage');

        $io->writeln('Indexing metapackages: ');
        $progressBar = $io->createProgressBar(count($names));

        $this->indexRequirements($progressBar, $names, $packages);

        $progressBar->finish();
        $io->newLine();
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
        $response = $this->http()->request('GET', 'https://packagist.org/packages/list.json?type='.$type);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Response error. Status code %s', $response->getStatusCode()));
        }

        $data = json_decode($response->getBody(), true);

        return $data['packageNames'];
    }

    private function getPackage(string $name): array
    {
        $response = $this->http()->request('GET', 'https://packagist.org/packages/'.$name.'.json');

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Response error. Status code %s', $response->getStatusCode()));
        }

        $data = json_decode($response->getBody(), true);

        return $data['package'];
    }

    private function extractSearchData(array $package): array
    {
        $version = reset($package['versions']);

        $data = [
            'name' => $package['name'],
            'description' => $package['description'],
            'keywords' => $version['keywords'],
            'homepage' => $version['homepage'] ?? ($package['repository'] ?? ''),
            'license' => $version['license'] ?? '',
            'downloads' => $package['downloads']['total'] ?? 0,
            'stars' => $package['favers'] ?? 0,
            'managed' => $package['type'] !== 'contao-bundle' || isset($version['extra']['contao-manager-plugin']),
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
            $client = new AlgoliaClient(@getenv('ALGOLIA_APP', true), @getenv('ALGOLIA_KEY', true));
            $this->index = $client->initIndex(@getenv('ALGOLIA_INDEX', true));
        }

        $this->index->saveObjects($objects, 'name');
    }

    private function http()
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $cacheDir = __DIR__.'/../../cache/http';

        (new Filesystem())->mkdir($cacheDir);

        $stack = HandlerStack::create();
        $storage = new Psr6CacheStorage(new FilesystemAdapter('', 60, $cacheDir));
        $stack->push(new CacheMiddleware(new PublicCacheStrategy($storage)), 'cache');

        $this->client = new GuzzleClient(['handler' => $stack]);

        return $this->client;
    }
}
