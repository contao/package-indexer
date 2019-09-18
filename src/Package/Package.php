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
    private $managed = false;

    /**
     * @var bool
     */
    private $abandoned = false;

    /**
     * @var bool
     */
    private $private = false;

    /**
     * @var string
     */
    private $replacement = '';

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

    public function isManaged(): bool
    {
        return $this->managed;
    }

    public function setManaged(bool $managed): self
    {
        $this->managed = $managed;

        return $this;
    }

    public function isAbandoned(): bool
    {
        return $this->abandoned;
    }

    public function setAbandoned(bool $abandoned): self
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

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function setReplacement(string $replacement): self
    {
        $this->replacement = $replacement;

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
            'objectID' => $this->getName().('en' !== $language ? '/'.$language : ''),
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
            'abandoned' => $this->isAbandoned(),
            'private' => $this->isPrivate(),
            'replacement' => $this->getReplacement(),
            'suggest' => $this->getSuggest(),
            'logo' => $this->getLogo(),
            'languages' => $allLanguages ?? [$language],
        ];

        // Language specific
        foreach ($this->getMetaForLanguage($language) as $k => $v) {
            if (isset($data[$k])) {
                $data[$k] = $v;
            }
        }

        return $data;
    }
}
