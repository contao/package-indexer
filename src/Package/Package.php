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

class Package
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var array
     */
    private $keywords;

    /**
     * @var string
     */
    private $homepage;

    /**
     * @var array
     */
    private $support;

    /**
     * @var array
     */
    private $versions;

    /**
     * @var array
     */
    private $license;

    /**
     * @var int
     */
    private $downloads;

    /**
     * @var int
     */
    private $stars;

    /**
     * @var bool
     */
    private $supported;

    /**
     * @var bool
     */
    private $managed;

    /**
     * @var bool
     */
    private $abandoned;

    /**
     * @var string
     */
    private $replacement;

    /**
     * @var string
     */
    private $logo = '';

    /**
     * @var array
     */
    private $meta = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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

    public function getStars(): int
    {
        return $this->stars;
    }

    public function setStars(int $stars): self
    {
        $this->stars = $stars;

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

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function setReplacement(string $replacement): self
    {
        $this->replacement = $replacement;

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

    public function getForAlgolia(string $language): array
    {
        $data = [
            'name' => $this->getName(),
            'title' => $this->getName(),
            'description' => $this->getDescription(),
            'keywords' => $this->getKeywords(),
            'homepage' => $this->getHomepage(),
            'support' => $this->getSupport(),
            'license' => $this->getLicense(),
            'downloads' => $this->getDownloads(),
            'stars' => $this->getStars(),
            'supported' => $this->isSupported(),
            'managed' => $this->isManaged(),
            'abandoned' => $this->isAbandoned(),
            'replacement' => $this->getReplacement(),
            'logo' => $this->getLogo(),
        ];

        // Language specific
        $meta = $this->getMetaForLanguage($language);

        foreach ($meta as $k => $v) {
            if (isset($data[$k])) {
                $data[$k] = $v;
            }
        }

        return $data;
    }
}
