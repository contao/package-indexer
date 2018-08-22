<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2018, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Package;

use App\Indexer;
use App\Packagist;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Factory
{
    /**
     * @var string
     */
    private $metaDataDir;

    /**
     * @var Packagist
     */
    private $packagist;

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var array
     */
    private $cache = [];

    public function __construct(string $metaDataDir, Packagist $packagist)
    {
        $this->metaDataDir = $metaDataDir;
        $this->packagist = $packagist;
        $this->fs = new Filesystem();
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

        $package = new Package();
        $this->setBasicData($data, $package);

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

        $package = new MetaPackage();
        $this->setBasicData($data, $package);

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

    private function setBasicData(array $data, Package $package)
    {
        $versions = [];

        $latest = end($data['p']);

        foreach ($data['packages']['versions'] as $version => $versionData) {
            $versions[] = $version;
        }

        sort($versions);

        $package->setName($data['packages']['name']);
        $package->setTitle($data['packages']['name']);
        $package->setDescription($latest['description'] ?? '');
        $package->setKeywords($latest['keywords'] ?? []);
        $package->setHomepage($latest['homepage'] ?? '');
        $package->setSupport($latest['support'] ?? []);
        $package->setVersions($versions);
        $package->setLicense($latest['license'] ?? []);
        $package->setDownloads((int) ($data['packages']['downloads']['total'] ?? 0));
        $package->setStars((int) ($data['packages']['favers'] ?? 0));
        $package->setSupported($this->isSupported($data['packages']['versions']));
        $package->setManaged($this->isManaged($data['packages']['versions']));
        $package->setAbandoned(isset($data['packages']['abandoned']));
        $package->setReplacement($data['packages']['replacement'] ?? '');

        $this->addLogo($package);
        $this->addMeta($package);
    }

    private function isSupported(array $versionsData): bool
    {
        foreach ($versionsData as $version => $versionData) {
            if (isset($versionData['require']['contao/core-bundle'])
                || 'contao-component' === $versionData['type']
            ) {
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
            $data = $this->extractMetadata($package->getName(), $language);
            $meta[$language] = $this->filterMetadata($data);
        }

        $package->setMeta($meta);
    }

    private function addLogo(Package $package): void
    {
        list($vendor, $name) = explode('/', $package->getName(), 2);
        $image = sprintf('%s/%s/logo.svg', $vendor, $name);

        if (!$this->fs->exists($this->metaDataDir.'/'.$image)) {
            $image = sprintf('%s/logo.svg', $vendor);

            if (!$this->fs->exists($this->metaDataDir.'/'.$image)) {
                return;
            }
        }

        // if bigger than 5kb use raw url
        if (@filesize($this->metaDataDir.'/'.$image) > (5 * 1024)) {
            $logo = sprintf(
                'https://rawgit.com/contao/package-metadata/master/meta/'.$image,
                $package->getName()
            );
        } else {
            $logo = sprintf(
                'data:image/svg+xml;base64,%s',
                base64_encode(file_get_contents($this->metaDataDir.'/'.$image))
            );
        }

        $package->setLogo($logo);
    }

    private function filterMetadata(array $data): array
    {
        return array_intersect_key(
            $data,
            array_flip(['title', 'description', 'keywords', 'homepage', 'support'])
        );
    }

    private function extractMetadata(string $packageName, string $language): array
    {
        $file = $this->metaDataDir.'/'.$packageName.'/'.$language.'.yml';

        try {
            $data = Yaml::parseFile($file);

            return (array_key_exists($language, $data) && \is_array($data[$language])) ? $data[$language] : [];
        } catch (ParseException $e) {
            return [];
        }
    }
}
