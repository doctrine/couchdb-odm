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

    /**
     * @param  string $id
     * @return Response
     */
    public function findDocument($id)
    {
        $documentPath = '/' . $this->config->getDatabaseName() . '/' . urlencode($id);
        $response =  $this->config->getHttpClient()->request( 'GET', $documentPath );

        if ($response->status == 404) {
            throw new DocumentNotFoundException($id);
        }
        return $response;
    }

    /**
     * @param array $data
     * @return Response
     */
    public function postDocument(array $data)
    {
        return $this->config->getHttpClient()
                            ->request('POST', '/' . $this->config->getDatabaseName() , json_encode($data));
    }

    public function putDocument(array $data, $id, $rev = null)
    {
        $data['_id'] = $id;
        if ($rev) {
            $data['_rev'] = $rev;
        }
        return $this->config->getHttpClient()
                    ->request('PUT', '/' . $this->config->getDatabaseName() . '/' . $id , json_encode($data));
    }

    public function deleteDocument($id, $rev)
    {
        return $this->config->getHttpClient()
                    ->request('DELETE', '/' . $this->config->getDatabaseName() . '/' . $id . '?rev=' . $rev);
    }
}