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
    protected function getHashRelevantInfo(Package $package): array
    {
        $info = parent::getHashRelevantInfo($package);
        $info[] = $package->getFavers();
        $info[] = $package->getDownloads();

        return $info;
    }
}
