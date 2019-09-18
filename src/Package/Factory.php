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

    public function createBasicFromPackagist(string $name): ?Package
    {
        $cacheKey = 'basic-'.$name;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $data = $this->packagist->getPackageData($name);

        if (0 === \count($data)) {
            return null;
        }

        $package = new Package($data['packages']['name']);
        $this->setBasicDataFromPackagist($data, $package);

        return $this->cache[$cacheKey] = $package;
    }

    public function createMetaFromPackagist(string $name): ?MetaPackage
    {
        $cacheKey = 'meta-'.$name;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $data = $this->packagist->getPackageData($name);

        if (0 === \count($data)) {
            return null;
        }

        $package = new MetaPackage($data['packages']['name']);
        $this->setBasicDataFromPackagist($data, $package);

        $requires = [];

        foreach ($data['packages']['versions'] as $version) {
            if (!isset($version['require'])) {
                continue;
            }

            foreach (array_keys($version['require']) as $require) {
                $requires[$require] = true;
            }
        }

        $requires = array_keys($requires);

        foreach ($requires as $require) {
            $reqPackage = $this->createBasicFromPackagist($require);

            if (null === $reqPackage) {
                continue;
            }

            if (!$package->isSupported() && $reqPackage->isSupported()) {
                $package->setSupported(true);
            }

            if (!$package->isManaged() && $reqPackage->isManaged()) {
                $package->setManaged(true);
            }
        }

        $package->setRequiredPackagesAccrossVersions($requires);

        return $this->cache[$cacheKey] = $package;
    }

    public function createPrivate(string $name): Package
    {
        $package = new Package($name);
        $package->setTitle($name);
        $package->setSupported(true);
        $package->setManaged(true);
        $package->setLicense(['proprietary']);
        $package->setPrivate(true);

        $package->setLogo($this->metaData->getLogoForPackage($package));
        $this->addMeta($package);

        return $package;
    }

    private function setBasicDataFromPackagist(array $data, Package $package): void
    {
        $versions = [];

        $latest = end($data['p']);

        foreach ($data['packages']['versions'] as $version => $versionData) {
            $versions[] = $version;
        }

        sort($versions);

        $package->setTitle($package->getName());
        $package->setDescription($latest['description'] ?? '');
        $package->setKeywords($latest['keywords'] ?? []);
        $package->setHomepage($latest['homepage'] ?? '');
        $package->setSupport($latest['support'] ?? []);
        $package->setVersions($versions);
        $package->setLicense($latest['license'] ?? []);
        $package->setDownloads((int) ($data['packages']['downloads']['total'] ?? 0));
        $package->setFavers((int) ($data['packages']['favers'] ?? 0));
        $package->setReleased($data['packages']['time'] ?? '');
        $package->setUpdated($latest['time'] ?? '');
        $package->setSupported($this->isSupported($data['packages']['versions']));
        $package->setManaged($this->isManaged($data['packages']['versions']));
        $package->setAbandoned(isset($data['packages']['abandoned']));
        $package->setReplacement($data['packages']['replacement'] ?? '');
        $package->setSuggest($latest['suggest'] ?? []);
        $package->setPrivate(false);

        $package->setLogo($this->metaData->getLogoForPackage($package));
        $this->addMeta($package);
    }

    private function isSupported(array $versionsData): bool
    {
        foreach ($versionsData as $version => $versionData) {
            if ('contao-component' === $versionData['type'] || isset($versionData['require']['contao/core-bundle'])) {
                return true;
            }
        }

        return false;
    }

    private function isManaged(array $versionsData): bool
    {
        foreach ($versionsData as $version => $versionData) {
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
}
