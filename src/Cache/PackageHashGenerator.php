<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Cache;

use App\Package\Package;

class PackageHashGenerator
{
    public function getHash(Package $package): string
    {
        return sha1(json_encode($this->getHashRelevantInfo($package)));
    }

    protected function getHashRelevantInfo(Package $package): array
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
        $info[] = $package->getAbandoned();
        $info[] = $package->isPrivate();
        $info[] = $package->getReplacement();
        $info[] = $package->getLogo();
        $info[] = $package->getMeta();

        return $info;
    }
}
