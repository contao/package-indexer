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
     * @var Filesystem
     */
    private $fs;

    public function __construct(string $metaDataDir)
    {
        $this->metaDataDir = $metaDataDir;
        $this->fs = new Filesystem();
    }

    public function createBasicFromPackagist(array $data): ?Package
    {
        $package = new Package();
        $this->setBasicData($data, $package);

        return $package;
    }

    public function createMetaFromPackagist(array $data): ?MetaPackage
    {
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

        $package->setRequiredPackagesAccrossVersions(array_keys($requires));

        return $package;
    }

    private function setBasicData(array $data, Package $package)
    {
        $supported = false;
        $managed = false;
        $versions = [];

        $latest = end($data['p']);

        foreach ($data['packages']['versions'] as $version => $versionData) {
            if (!$supported
                && (
                    isset($versionData['require']['contao/core-bundle'])
                    || 'contao-component' === $versionData['type']
                )
            ) {
                $supported = true;
            }

            if (!$managed
                && ('contao-bundle' === $versionData['type']
                || 'contao-component' === $versionData['type']
                || isset($versionData['extra']['contao-manager-plugin']))
            ) {
                $managed = true;
            }

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
        $package->setDownloads($data['packages']['downloads']['total'] ?? 0);
        $package->setStars($data['packages']['favers'] ?? 0);
        $package->setSupported($supported);
        $package->setManaged($managed);
        $package->setAbandoned(isset($data['packages']['abandoned']));
        $package->setReplacement($data['packages']['replacement'] ?? '');

        $this->addLogo($package);
        $this->addMeta($package);
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
