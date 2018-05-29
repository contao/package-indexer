<?php

namespace Contao\PackageIndexer\Algolia;

use AlgoliaSearch\Client;
use AlgoliaSearch\Index;

class V1Index implements IndexInterface
{
    private const INDEX_NAME = 'v1';

    /**
     * @var Index
     */
    private $index;

    /**
     * Constructor.
     *
     * @param Client $client
     * @param bool   $clearIndex
     *
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function __construct(Client $client, bool $clearIndex = false)
    {
        $this->index = $client->initIndex(self::INDEX_NAME);

        if ($clearIndex) {
            $this->index->clearIndex();
        }
    }

    public function push(array $packages): void
    {
        $this->index->saveObjects($packages, 'name');
    }
}
