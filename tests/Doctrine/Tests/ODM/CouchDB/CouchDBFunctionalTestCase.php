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

    protected $logger;

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

            $this->logger = new \Doctrine\CouchDB\HTTP\LoggingClient($this->httpClient);
        }

        return $this->logger;
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
        $couchDBClient = $this->createCouchDBClient();
        $httpClient = $couchDBClient->getHttpClient();
        $database = $couchDBClient->getDatabase();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);

        $reader = new \Doctrine\Common\Annotations\SimpleAnnotationReader();
        $reader->addNamespace('Doctrine\ODM\CouchDB\Mapping\Annotations');
        $paths = __DIR__ . "/../../Models";
        $metaDriver = new AnnotationDriver($reader, $paths);

        $config = $this->createConfiguration($metaDriver);

        return DocumentManager::create($couchDBClient, $config);
    }

    public function createConfiguration($metaDriver)
    {
        $config = new Configuration();
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setAutoGenerateProxyClasses(true);
        $config->setMetadataDriverImpl($metaDriver);
        $config->setMetadataCacheImpl(new ArrayCache);
        $config->setLuceneHandlerName('_fti');

        return $config;
    }
}
