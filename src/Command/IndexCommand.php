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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

        $this->indexPackages($packages);
        $this->indexRequirements($this->getPackageNames('metapackage'), $packages);
    }

    private function indexPackages(array $names): void
    {
        foreach (array_chunk($names, 100) as $chunk) {
            $objects = [];

            foreach ($chunk as $name) {
                $objects[] = $this->extractSearchData($this->getPackage($name));
            }

            $this->index($objects);
            unset($objects);
        }
    }

    private function indexRequirements(array $names, array $required): void
    {
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
        }

        $this->index($objects);

        if (0 !== count($children)) {
            $this->indexRequirements($children, $required);
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
