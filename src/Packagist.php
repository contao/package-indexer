<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2018, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App;

use App\Package\Factory;
use App\Package\MetaPackage;
use App\Package\Package;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Log\LoggerInterface;

class Packagist
{
    /**
     * Blacklisted packages that should not be found.
     */
    private const BLACKLIST = ['contao/installation-bundle', 'contao/module-devtools', 'contao/module-repository', 'contao/contao'];

    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Factory
     */
    private $packageFactory;

    public function __construct(LoggerInterface $logger, Client $client, Factory $packageFactory)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->packageFactory = $packageFactory;
    }

    public function getPackageNames(string $type): array
    {
        try {
            $data = $this->getJson('https://packagist.org/packages/list.json?type='.$type);
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf('Error fetching package names of type "%s"', $type));

            return [];
        }

        return array_diff($data['packageNames'], self::BLACKLIST);
    }

    public function getPackage(string $name): ?Package
    {
        return $this->packageFactory->createBasicFromPackagist($this->getPackageData($name));
    }

    public function getMetaPackage(string $name): ?MetaPackage
    {
        return $this->packageFactory->createMetaFromPackagist($this->getPackageData($name));
    }

    private function getPackageData(string $name): array
    {
        try {
            $data['packages'] = $this->getJson('https://packagist.org/packages/'.$name.'.json')['package'];
            $packagesData = $this->getJson('https://packagist.org/packages/'.$name.'.json');

            if (!isset($packagesData['package'])) {
                return [];
            }

            $data['packages'] = $packagesData['package'];
            $data['p'] = $this->getJson('https://repo.packagist.org/p/'.$name.'.json')['packages'][$name];
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf('Error fetching package "%s"', $name));

            return [];
        }

        return $data;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getJson(string $uri): array
    {
        $response = $this->client->request('GET', $uri);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('Response error. Status code %s', $response->getStatusCode()));
        }

        if (CacheMiddleware::HEADER_CACHE_HIT === $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)) {
            $this->logger->debug(sprintf('Cache: hit [%s]', $uri));
        } else {
            $this->logger->debug(sprintf('Cache: miss [%s]', $uri));
        }

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
