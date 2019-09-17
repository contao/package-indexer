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

class MetaAwarePackageHashGenerator extends PackageHashGenerator
{
    protected function getHashRelevantInfo(Package $package, string $language): array
    {
        $info = parent::getHashRelevantInfo($package, $language);
        $info[] = $package->getStars();
        $info[] = $package->getDownloads();

        return $info;
    }
}
