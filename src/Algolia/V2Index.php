<?php

namespace Contao\PackageIndexer\Algolia;

use AlgoliaSearch\Client;
use AlgoliaSearch\Index;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class V2Index implements IndexInterface
{
    private const INDEX_PREFIX = 'v2_';
    private const METADATA_ROOT = __DIR__.'/../../metadata/meta';

    /**
     * @var Index
     */
    private $index;

    /**
     * @var string
     */
    private $language;

    /**
     * @var null|Filesystem
     */
    private $filesystem;

    /**
     * Constructor.
     *
     * @param Client          $client
     * @param string          $language
     * @param Filesystem|null $filesystem
     *
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function __construct(Client $client, string $language, Filesystem $filesystem = null)
    {
        $this->index = $client->initIndex(self::INDEX_PREFIX.$language);
        $this->language = $language;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    public function push(array $packages): void
    {
        foreach ($packages as &$package) {
            $this->addMeta($package);
            $this->addLogo($package);
        }
        unset($package);

        $this->index->saveObjects($packages, 'name');
    }

    private function addMeta(array &$package): void
    {
        $package = array_merge(
            $package,
            $this->filterMetadata($this->extractMetadata($package['name']))
        );
    }

    private function addLogo(array &$package): void
    {
        list ($vendor, $name) = explode('/', $package['name'], 2);
        $image = sprintf('%s/%s/logo.svg', $vendor, $name);

        if (!$this->filesystem->exists(self::METADATA_ROOT.'/'.$image)) {
            $image = sprintf('%s/logo.svg', $vendor);

            if (!$this->filesystem->exists(self::METADATA_ROOT.'/'.$image)) {
                return;
            }
        }

        // if bigger than 5kb use raw url
        if (@filesize(self::METADATA_ROOT.'/'.$image) > (5 * 1024)) {
            $package['logo'] = sprintf(
                'https://rawgit.com/contao/package-metadata/master/meta/'.$image,
                $package['name']
            );
            return;
        }

        $package['logo'] = sprintf(
            "data:image/svg+xml;base64,%s",
            base64_encode(file_get_contents(self::METADATA_ROOT.'/'.$image))
        );
    }

    private function filterMetadata(array $data): array
    {
        return array_intersect_key(
            $data,
            array_flip(['title', 'description', 'keywords', 'homepage', 'support'])
        );
    }

    /**
     * @param string $packageName
     *
     * @return array
     */
    private function extractMetadata($packageName)
    {
        $file = self::METADATA_ROOT.'/'.$packageName.'/'.$this->language.'.yml';

        try {
            $data = Yaml::parseFile($file);

            return (array_key_exists($this->language, $data) && \is_array($data[$this->language])) ? $data[$this->language] : [];
        } catch (ParseException $e) {
            return [];
        }
    }
}
