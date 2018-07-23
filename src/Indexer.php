<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2018, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App;

use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Client;
use AlgoliaSearch\Index;
use App\Cache\PackageHashGenerator;
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
    public const LANGUAGES = ['en', 'de', 'br', 'cs', 'es', 'fa', 'fr', 'ja', 'lv', 'nl', 'pl', 'ru', 'sr', 'zh'];
    private const CACHE_PREFIX = 'package-indexer';
    private const INDEX_PREFIX = 'v2_';

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
     * @var Index[]
     */
    private $indexes;

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

    public function __construct(LoggerInterface $logger, Packagist $packagist, Client $client, CacheItemPoolInterface $cacheItemPool, PackageHashGenerator $packageHashGenerator)
    {
        $this->packagist = $packagist;
        $this->client = $client;
        $this->logger = $logger;
        $this->cacheItemPool = $cacheItemPool;
        $this->packageHashGenerator = $packageHashGenerator;
    }

    public function index(string $package = null, bool $dryRun = false)
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

        $this->indexPackages($dryRun);
    }

    private function collectPackages(array $packageNames): void
    {
        foreach ($packageNames as $packageName) {
            $package = $this->packagist->getPackage($packageName);

            if (null !== $package) {
                $this->packages[$packageName] = $package;
            }
        }
    }

    private function collectMetapackages(): void
    {
        $packages = array_keys($this->packages);

        foreach ($this->packagist->getPackageNames('metapackage') as $packageName) {
            $metaPackage = $this->packagist->getMetaPackage($packageName);

            if (null === $metaPackage) {
                continue;
            }

            if ($metaPackage->requiresOneOf($packages)) {
                $this->packages[$packageName] = $metaPackage;
            }
        }
    }

    private function indexPackages(bool $dryRun): void
    {
        if (0 === \count($this->packages)) {
            return;
        }

        $this->createIndexes();
        $packagesPerLanguage = [];

        foreach (self::LANGUAGES as $language) {
            if (!isset($packagesPerLanguage[$language])) {
                $packagesPerLanguage[$language] = [];
            }

            // Ignore the ones that do not need any update
            foreach ($this->packages as $packageName => $package) {
                $hash = self::CACHE_PREFIX.'-'.
                    $language.'-'.
                    $this->packageHashGenerator->getHash($package, $language);

                $cacheItem = $this->cacheItemPool->getItem($hash);

                if (!$cacheItem->isHit()) {
                    $hit = false;
                    $cacheItem->set(true);
                    $this->cacheItemPool->saveDeferred($cacheItem);
                    $packagesPerLanguage[$language][] = $package;
                } else {
                    $hit = true;
                }

                $this->logger->debug(sprintf('Cache entry for package "%s" and language "%s" was a %s (hash: %s)',
                    $packageName,
                    $language,
                    $hit ? 'hit' : 'miss',
                    $hash
                ));
            }
        }

        foreach ($packagesPerLanguage as $language => $packages) {
            foreach (array_chunk($packages, 100) as $chunk) {
                $objects = [];

                /** @var Package $package */
                foreach ($chunk as $package) {
                    $objects[] = $package->getForAlgolia($language);
                }

                if (!$dryRun) {
                    $this->indexes[$language]->saveObjects($objects, 'name');
                } else {
                    $this->logger->debug(sprintf('Objects: %s', json_encode($objects)));
                }
            }

            $this->logger->info(sprintf('Updated "%s" package(s) for language "%s".',
                \count($packages),
                $language)
            );
        }

        $this->cacheItemPool->commit();
    }

    private function createIndexes(): void
    {
        if (null === $this->indexes) {
            foreach (self::LANGUAGES as $language) {
                try {
                    $this->indexes[$language] = $this->client->initIndex(self::INDEX_PREFIX.$language);
                } catch (AlgoliaException $e) {
                }
            }
        }
    }
}
