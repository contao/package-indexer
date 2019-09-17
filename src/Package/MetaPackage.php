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

class MetaPackage extends Package
{
    /**
     * @return array
     */
    private $requiredPackagesAccrossVersions = [];

    /**
     * @return MetaPackage
     */
    public function setRequiredPackagesAccrossVersions($requiredPackagesAccrossVersions)
    {
        $this->requiredPackagesAccrossVersions = $requiredPackagesAccrossVersions;

        return $this;
    }

    public function requiresOneOf(array $packageNames): bool
    {
        return 0 !== \count(array_intersect($this->requiredPackagesAccrossVersions, $packageNames));
    }
}
