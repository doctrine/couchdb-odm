<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\SocketClient;
use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Configuration;
use Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Annotations\AnnotationReader;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    /**
     * @return \Doctrine\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            if (isset($GLOBALS['DOCTRINE_COUCHDB_CLIENT'])) {
                $this->httpClient = new $GLOBALS['DOCTRINE_COUCHDB_CLIENT'];
            } else {
                $this->httpClient = new SocketClient();
            }
        }
        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }
    
    public function createCouchDBClient()
    {
        return new CouchDBClient($this->getHttpClient(), $this->getTestDatabase());
    }

    public function createDocumentManager()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);

        $reader = new AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
        $paths = __DIR__ . "/../../Models";
        $metaDriver = new AnnotationDriver($reader, $paths);

        $config = new Configuration();
        $config->setDatabase($database);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setMetadataDriverImpl($metaDriver);
        $setMetadataCacheImpl = $config->setMetadataCacheImpl(new ArrayCache);
        $config->setHttpClient($httpClient);
        $config->setLuceneHandlerName('_fti');

        return DocumentManager::create($config);
    }
}