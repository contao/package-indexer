<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2018, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Cache;

use App\Package\Package;

class PackageHashGenerator
{
    public function getHash(Package $package, string $language): string
    {
        return sha1(json_encode($this->getHashRelevantInfo($package, $language)));
    }

    protected function getHashRelevantInfo(Package $package, string $language): array
    {
        $info = [];
        $info[] = $package->getName();
        $info[] = $package->getDescription();
        $info[] = $package->getKeywords();
        $info[] = $package->getHomepage();
        $info[] = $package->getSupport();
        $info[] = $package->getVersions();
        $info[] = $package->getLicense();
        $info[] = $package->isSupported();
        $info[] = $package->isManaged();
        $info[] = $package->isAbandoned();
        $info[] = $package->getReplacement();
        $info[] = $package->getLogo();
        $info[] = $package->getMetaForLanguage($language);

        return $info;
    }
}
