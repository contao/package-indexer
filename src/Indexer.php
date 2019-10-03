<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App;

use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Client;
use AlgoliaSearch\Index;
use App\Cache\PackageHashGenerator;
use App\Package\Factory;
use App\Package\Package;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class Indexer
{
    /**
     * Languages for the search index.
     *
     * @see https://github.com/contao/contao-manager/blob/master/src/i18n/locales.js
     */
    public const LANGUAGES = ['en', 'de', 'br', 'cs', 'es', 'fa', 'fr', 'it', 'ja', 'lv', 'nl', 'pl', 'pt', 'ru', 'sr', 'zh'];
    private const CACHE_PREFIX = 'package-indexer';
    private const INDEX_NAME = 'v3_packages';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Packagist
     */
    private $packagist;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var Package[]
     */
    private $packages = [];

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @var PackageHashGenerator
     */
    private $packageHashGenerator;

    /**
     * @var Factory
     */
    private $packageFactory;

    /**
     * @var MetaDataRepository
     */
    private $metaDataRepository;

    public function __construct(LoggerInterface $logger, Packagist $packagist, Factory $packageFactory, Client $client, CacheItemPoolInterface $cacheItemPool, PackageHashGenerator $packageHashGenerator, MetaDataRepository $metaDataRepository)
    {
        $this->packagist = $packagist;
        $this->client = $client;
        $this->logger = $logger;
        $this->cacheItemPool = $cacheItemPool;
        $this->packageHashGenerator = $packageHashGenerator;
        $this->packageFactory = $packageFactory;
        $this->metaDataRepository = $metaDataRepository;
    }

    public function index(string $package = null, bool $dryRun = false, bool $ignoreCache = false, $clearIndex = false)
    {
        $this->packages = [];

        if (null !== $package) {
            $packageNames = [$package];
        } else {
            $packageNames = array_unique(array_merge(
                $this->packagist->getPackageNames('contao-bundle'),
                $this->packagist->getPackageNames('contao-module'),
                $this->packagist->getPackageNames('contao-component')
            ));
        }

        $this->collectPackages($packageNames);

        if (null === $package) {
            $this->collectAdditionalPackages();
        }

        $this->indexPackages($dryRun, $ignoreCache, $clearIndex);

        // If the index was not cleared completely, delete old/removed packages
        if (!$clearIndex && null === $package) {
            $this->deleteRemovedPackages($dryRun);
        }
    }

    private function deleteRemovedPackages(bool $dryRun): void
    {
        $packagesToDeleteFromIndex = [];

        $this->createIndex(false);
        foreach ($this->index->browse('', ['attributesToRetrieve' => ['objectID']]) as $item) {
            // Check if object still exists in collected packages
            $objectID = $item['objectID'];
            $name = substr($objectID, 0, -3);
            if (!isset($this->packages[$name])) {
                $packagesToDeleteFromIndex[] = $objectID;
            }
        }

        if (0 === \count($packagesToDeleteFromIndex)) {
            return;
        }

        if (!$dryRun) {
            foreach ($packagesToDeleteFromIndex as $objectID) {
                $this->index->deleteObject($objectID);
            }
        } else {
            $this->logger->debug(sprintf('Objects to delete from index: %s', json_encode($packagesToDeleteFromIndex)));
        }
    }

    private function collectPackages(array $packageNames): void
    {
        foreach ($packageNames as $packageName) {
            $package = $this->packageFactory->create($packageName);

            if (null === $package || !$package->isSupported()) {
                $this->logger->debug($packageName.' is not supported.');
                continue;
            }

            $this->packages[$packageName] = $package;
            $this->logger->debug('Added '.$packageName);
        }
    }

    private function collectAdditionalPackages(): void
    {
        $publicPackages = array_keys($this->packages);
        $availablePackages = $this->metaDataRepository->getPackageNames();

        $additionalPackages = array_diff($availablePackages, $publicPackages);

        if (0 !== \count($additionalPackages)) {
            $this->collectPackages($additionalPackages);
        }
    }

    private function indexPackages(bool $dryRun, bool $ignoreCache, bool $clearIndex): void
    {
        if (0 === \count($this->packages)) {
            return;
        }

        $this->createIndex($clearIndex);
        $packages = [];

        // Ignore the ones that do not need any update
        foreach ($this->packages as $packageName => $package) {
            $hash = self::CACHE_PREFIX.'-'.$this->packageHashGenerator->getHash($package);

            $cacheItem = $this->cacheItemPool->getItem($hash);

            if (!$ignoreCache) {
                if (!$cacheItem->isHit()) {
                    $hitMsg = 'miss';
                    $cacheItem->set(true);
                    $this->cacheItemPool->saveDeferred($cacheItem);
                    $packages[] = $package;
                } else {
                    $hitMsg = 'hit';
                }
            } else {
                $packages[] = $package;
                $hitMsg = 'ignored';
            }

            $this->logger->debug(sprintf('Cache entry for package "%s" was %s (hash: %s)',
                    $packageName,
                    $hitMsg,
                    $hash
                ));
        }

        foreach (array_chunk($packages, 100) as $chunk) {
            $objects = [];

            /** @var Package $package */
            foreach ($chunk as $package) {
                $languageKeys = array_unique(array_merge(['en'], array_keys(array_filter($package->getMeta()))));

                foreach ($languageKeys as $language) {
                    $languages = [$language];

                    if ('en' === $language) {
                        $languages = array_merge(['en'], array_diff(self::LANGUAGES, $languageKeys));
                    }

                    $objects[] = $package->getForAlgolia($languages);
                }
            }

            if (!$dryRun) {
                $this->index->saveObjects($objects);
            } else {
                $this->logger->debug(sprintf('Objects to index: %s', json_encode($objects)));
            }
        }

        $this->logger->info(sprintf('Updated "%s" package(s).', \count($packages)));

        $this->cacheItemPool->commit();
    }

    private function createIndex(bool $clearIndex): void
    {
        if (null === $this->index) {
            try {
                $this->index = $this->client->initIndex(self::INDEX_NAME);

                if ($clearIndex) {
                    $this->index->clearIndex();
                }
            } catch (AlgoliaException $e) {
            }
        }
    }
}
