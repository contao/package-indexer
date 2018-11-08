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

use App\Package\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class MetaDataRepository
{
    /**
     * @var string
     */
    private $metaDataDir;

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * Metadata constructor.
     */
    public function __construct(string $metaDataDir)
    {
        $this->metaDataDir = $metaDataDir;
        $this->fs = new Filesystem();
    }

    public function getDir(): string
    {
        return $this->metaDataDir;
    }

    public function getMetaDataDir(): string
    {
        return $this->getDir().'/meta';
    }

    public function getPackageNames(): array
    {
        $names = [];

        $finder = new Finder();
        $finder->directories()->in($this->getMetaDataDir())->depth('== 1');

        foreach ($finder as $dir) {
            $names[] = basename($dir->getPath()).'/'.$dir->getBasename();
        }

        return $names;
    }

    public function getLogoForPackage(Package $package): string
    {
        list($vendor, $name) = explode('/', $package->getName(), 2);
        $image = sprintf('%s/%s/logo.svg', $vendor, $name);

        if (!$this->fs->exists($this->getMetaDataDir().'/'.$image)) {
            $image = sprintf('%s/logo.svg', $vendor);

            if (!$this->fs->exists($this->getMetaDataDir().'/'.$image)) {
                return '';
            }
        }

        // if bigger than 5kb use raw url
        if (@filesize($this->getMetaDataDir().'/'.$image) > (5 * 1024)) {
            $logo = sprintf(
                'https://rawgit.com/contao/package-metadata/master/meta/'.$image,
                $package->getName()
            );
        } else {
            $logo = sprintf(
                'data:image/svg+xml;base64,%s',
                base64_encode(file_get_contents($this->getMetaDataDir().'/'.$image))
            );
        }

        return $logo;
    }

    public function getMetaDataForPackage(Package $package, string $language): array
    {
        $file = $this->getMetaDataDir().'/'.$package->getName().'/'.$language.'.yml';

        try {
            $data = Yaml::parseFile($file);
            $data = (array_key_exists($language, $data) && \is_array($data[$language])) ? $data[$language] : [];

            return $this->filterMetadata($data);
        } catch (ParseException $e) {
            return [];
        }
    }

    private function filterMetadata(array $data): array
    {
        return array_intersect_key(
            $data,
            array_flip(['title', 'description', 'keywords', 'homepage', 'support'])
        );
    }
}
