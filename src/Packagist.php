<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Log\LoggerInterface;

class Packagist
{
    const PLATFORM_PACKAGE_REGEX = '{^(?:php(?:-64bit|-ipv6|-zts|-debug)?|hhvm|(?:ext|lib)-[^/ ]+)$}i';

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

    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->client = $client;
        $this->logger = $logger;
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

    public function getPackageData(string $name): array
    {
        if (preg_match(self::PLATFORM_PACKAGE_REGEX, $name)) {
            return [];
        }

        try {
            $packagesData = $this->getJson('https://packagist.org/packages/'.$name.'.json');

            if (!isset($packagesData['package'])) {
                return [];
            }

            $repoData = $this->getJson('https://repo.packagist.org/p/'.$name.'.json');

            if (!isset($repoData['packages'][$name])) {
                return [];
            }

            $data['packages'] = $packagesData['package'];
            $data['p'] = $repoData['packages'][$name];
        } catch (GuzzleException $e) {
            $this->logger->debug(sprintf('Error fetching package "%s"', $name));

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
