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
    private const INDEX_PREFIX = 'v3_';

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
        $this->collectMetapackages();
        $this->collectPrivatePackages();

        $this->indexPackages($dryRun, $ignoreCache, $clearIndex);

        // If the index was not cleared completely, delete old/removed packages
        if (!$clearIndex) {
            $this->deleteRemovedPackages($dryRun);
        }
    }

    private function deleteRemovedPackages(bool $dryRun): void
    {
        $packagesToDeleteFromIndex = [];

        foreach ($this->index->browse('', ['attributesToRetrieve' => ['objectID']]) as $item) {
            // Check if object still exists in collected packages
            if (!isset($this->packages[$item['objectID']])) {
                $packagesToDeleteFromIndex[] = $item['objectID'];
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
            $package = $this->packageFactory->createBasicFromPackagist($packageName);

            if (null !== $package) {
                $this->packages[$packageName] = $package;
            }
        }
    }

    private function collectMetapackages(): void
    {
        $packages = array_keys($this->packages);

        foreach ($this->packagist->getPackageNames('metapackage') as $packageName) {
            $metaPackage = $this->packageFactory->createMetaFromPackagist($packageName);

            if (null === $metaPackage) {
                continue;
            }

            if ($metaPackage->requiresOneOf($packages)) {
                $this->packages[$packageName] = $metaPackage;
            }
        }
    }

    private function collectPrivatePackages(): void
    {
        $publicPackages = array_keys($this->packages);
        $availablePackages = $this->metaDataRepository->getPackageNames();

        foreach (array_diff($availablePackages, $publicPackages) as $packageName) {
            $this->packages[$packageName] = $this->packageFactory->createPrivate($packageName);
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
                $languageKeys = array_keys($package->getMeta());
                // TODO does 'meta' always have an 'en' key?
                foreach ($languageKeys as $language) {
                    if ('en' === $language) {
                        $allLanguages = array_merge(['en'], array_diff(self::LANGUAGES, $languageKeys));
                    }

                    $objects[] = $package->getForAlgolia($language, $allLanguages ?? null);
                }
            }

            if (!$dryRun) {
                $this->index->saveObjects($objects, 'name');
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
                $this->index = $this->client->initIndex(self::INDEX_PREFIX.'packages');

                if ($clearIndex) {
                    $this->index->clearIndex();
                }
            } catch (AlgoliaException $e) {
            }
        }
    }
}
