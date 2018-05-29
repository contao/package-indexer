<?php

namespace Contao\PackageIndexer\Algolia;

interface IndexInterface
{
    public function push(array $packages): void;
}
