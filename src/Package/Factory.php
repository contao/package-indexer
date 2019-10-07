<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Package;

use App\Indexer;
use App\MetaDataRepository;
use App\Packagist;

class Factory
{
    /**
     * @var MetaDataRepository
     */
    private $metaData;

    /**
     * @var Packagist
     */
    private $packagist;

    /**
     * @var array
     */
    private $cache = [];

    public function __construct(MetaDataRepository $metaData, Packagist $packagist)
    {
        $this->metaData = $metaData;
        $this->packagist = $packagist;
    }

    public function create(string $name): Package
    {
        $cacheKey = 'basic-'.$name;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $data = $this->packagist->getPackageData($name);

        if (0 === \count($data)) {
            $package = $this->createPrivate($name);
        } else {
            $package = new Package($data['packages']['name']);
            $this->setBasicDataFromPackagist($data, $package);
        }

        return $this->cache[$cacheKey] = $package;
    }

    private function createPrivate(string $name): Package
    {
        $package = new Package($name);
        $package->setTitle($name);
        $package->setSupported(true);
        $package->setLicense(['proprietary']);
        $package->setPrivate(true);

        $package->setLogo($this->metaData->getLogoForPackage($package));
        $this->addMeta($package);

        return $package;
    }

    private function setBasicDataFromPackagist(array $data, Package $package): void
    {
        $latest = $this->findLatestVersion($data['p']);
        $versions = array_keys($data['packages']['versions']);
        // $data['p'] contains the non-cached data, while only $data['packages'] has the "support" metadata
        $latestPackages = $this->findLatestVersion($data['packages']['versions']);

        sort($versions);

        $package->setTitle($package->getName());
        $package->setDescription($latest['description'] ?? '');
        $package->setKeywords($latest['keywords'] ?? []);
        $package->setHomepage($latest['homepage'] ?? '');
        $package->setSupport($latestPackages['support'] ?? []);
        $package->setVersions($versions);
        $package->setLicense($latest['license'] ?? []);
        $package->setDownloads((int) ($data['packages']['downloads']['total'] ?? 0));
        $package->setFavers((int) ($data['packages']['favers'] ?? 0));
        $package->setReleased($data['packages']['time'] ?? '');
        $package->setUpdated($latest['time'] ?? '');
        $package->setSupported($this->isSupported($data['packages']['versions']));
        $package->setAbandoned($data['packages']['abandoned'] ?? false);
        $package->setSuggest($latest['suggest'] ?? []);
        $package->setPrivate(false);

        $package->setLogo($this->metaData->getLogoForPackage($package));
        $this->addMeta($package);
    }

    private function isSupported(array $versionsData): bool
    {
        foreach ($versionsData as $version => $versionData) {
            if ('contao-component' === $versionData['type']) {
                return true;
            }

            if (!isset($versionData['require']['contao/core-bundle'])) {
                continue;
            }

            if ('contao-bundle' !== $versionData['type'] || isset($versionData['extra']['contao-manager-plugin'])) {
                return true;
            }
        }

        return false;
    }

    private function addMeta(Package $package): void
    {
        $meta = [];

        foreach (Indexer::LANGUAGES as $language) {
            $meta[$language] = $this->metaData->getMetaDataForPackage($package, $language);
        }

        $package->setMeta($meta);
    }

    private function findLatestVersion(array $versions)
    {
        $latest = array_reduce(
            array_keys($versions),
            static function (?string $prev, string $curr) use ($versions) {
                if (null === $prev) {
                    return $curr;
                }

                if ('-dev' !== substr($prev, -4)
                    && 0 !== strpos($prev, 'dev-')
                    && (0 === strpos($curr, 'dev-') || '-dev' === substr($curr, -4))
                ) {
                    return $prev;
                }

                if ('-dev' !== substr($curr, -4)
                    && 0 !== strpos($curr, 'dev-')
                    && (0 === strpos($prev, 'dev-') || '-dev' === substr($prev, -4))
                ) {
                    return $curr;
                }

                return strtotime($versions[$prev]['time']) > strtotime($versions[$curr]['time']) ? $prev : $curr;
            }
        );

        return $versions[$latest];
    }
}
