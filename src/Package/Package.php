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

class Package
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $title = '';

    /**
     * @var string
     */
    private $description = '';

    /**
     * @var array
     */
    private $keywords = [];

    /**
     * @var string
     */
    private $homepage = '';

    /**
     * @var array
     */
    private $support = [];

    /**
     * @var array
     */
    private $versions = [];

    /**
     * @var array
     */
    private $license = [];

    /**
     * @var int
     */
    private $downloads = 0;

    /**
     * @var int
     */
    private $favers = 0;

    /**
     * @var string
     */
    private $released = '';

    /**
     * @var string
     */
    private $updated = '';

    /**
     * @var bool
     */
    private $supported = false;

    /**
     * @var bool
     */
    private $dependency = false;

    /**
     * @var string|bool
     */
    private $abandoned = '';

    /**
     * @var bool
     */
    private $private = false;

    /**
     * @var array
     */
    private $suggest = [];

    /**
     * @var string
     */
    private $logo = '';

    /**
     * @var array
     */
    private $meta = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getHomepage(): string
    {
        return $this->homepage;
    }

    public function setHomepage(string $homepage): self
    {
        $this->homepage = $homepage;

        return $this;
    }

    public function getSupport(): array
    {
        return $this->support;
    }

    public function setSupport(array $support): self
    {
        $this->support = $support;

        return $this;
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function setVersions(array $versions): self
    {
        $this->versions = $versions;

        return $this;
    }

    public function getLicense(): array
    {
        return $this->license;
    }

    public function setLicense(array $license): self
    {
        $this->license = $license;

        return $this;
    }

    public function getDownloads(): int
    {
        return $this->downloads;
    }

    public function setDownloads(int $downloads): self
    {
        $this->downloads = $downloads;

        return $this;
    }

    public function getFavers(): int
    {
        return $this->favers;
    }

    public function setFavers(int $favers): self
    {
        $this->favers = $favers;

        return $this;
    }

    public function getReleased(): string
    {
        return $this->released;
    }

    public function setReleased(string $released): self
    {
        $this->released = $released;

        return $this;
    }

    public function getUpdated(): string
    {
        return $this->updated;
    }

    public function setUpdated(string $updated): self
    {
        $this->updated = $updated;

        return $this;
    }

    public function isSupported(): bool
    {
        return $this->supported;
    }

    public function setSupported(bool $supported): self
    {
        $this->supported = $supported;

        return $this;
    }

    public function isDependency(): bool
    {
        return $this->dependency;
    }

    public function setDependency(bool $dependency): self
    {
        $this->dependency = $dependency;

        return $this;
    }

    public function getAbandoned()
    {
        return $this->abandoned;
    }

    public function setAbandoned($abandoned): self
    {
        $this->abandoned = $abandoned;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): self
    {
        $this->private = $private;

        return $this;
    }

    public function getSuggest(): array
    {
        return $this->suggest;
    }

    public function setSuggest(array $suggest): self
    {
        $this->suggest = $suggest;

        return $this;
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getMetaForLanguage(string $language): array
    {
        $meta = $this->getMeta();

        if (isset($meta[$language])) {
            return (array) $meta[$language];
        }

        return [];
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getForAlgolia(string $language, ?array $allLanguages): array
    {
        $data = [
            'objectID' => $this->getName().'/'.$language,
            'name' => $this->getName(),
            'title' => $this->getName(),
            'description' => $this->getDescription(),
            'keywords' => $this->getKeywords(),
            'homepage' => $this->getHomepage(),
            'support' => $this->getSupport(),
            'license' => $this->getLicense(),
            'downloads' => $this->getDownloads(),
            'favers' => $this->getFavers(),
            'released' => $this->getReleased(),
            'updated' => $this->getUpdated(),
            'dependency' => $this->isDependency(),
            'abandoned' => $this->getAbandoned(),
            'private' => $this->isPrivate(),
            'suggest' => $this->getSuggest(),
            'logo' => $this->getLogo(),
            'languages' => $allLanguages ?? [$language],
        ];

        // Language specific
        foreach ($this->getMetaForLanguage($language) as $k => $v) {
            if (isset($data[$k])) {
                if (isset($v[$k]) && \is_array($v[$k]) && array_keys($v[$k]) !== range(0, \count($v[$k]) - 1)) {
                    // Support associative arrays, as for example the "suggest" metadata.
                    // This supports partial translations and keeps original data if the metadata lacks translations.
                    foreach ($v[$k] as $kk => $vv) {
                        $data[$k][$kk] = $vv;
                    }
                    continue;
                }

                $data[$k] = $v;
            }
        }

        return $data;
    }
}
