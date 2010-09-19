<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\Client;

/**
 * Wraps around the HTTP Clients and offers several convenience methods to work with CouchDB.
 */
class CouchDBClient
{
    /**
     * @var Client
     */
    private $client = null;

    /**
     * @var Configuration
     */
    private $config = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function getUuids($count = 1)
    {
        $count = (int)$count;
        $response = $this->config->getHttpClient()->request('GET', '/_uuids?count=' . $count);

        if ($response->status != 200) {
            throw new CouchDBException("Could not retrieve UUIDs from CouchDB.");
        }

        return $response->body['uuids'];
    }
}