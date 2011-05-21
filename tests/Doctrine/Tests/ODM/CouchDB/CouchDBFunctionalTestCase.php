<?php

namespace Doctrine\Tests\ODM\CouchDB;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    /**
     * @return \Doctrine\ODM\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            if (isset($GLOBALS['DOCTRINE_COUCHDB_CLIENT'])) {
                $this->httpClient = new $GLOBALS['DOCTRINE_COUCHDB_CLIENT'];
            } else {
                $this->httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();
            }
        }
        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }

    public function createDocumentManager()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);

        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
        $paths = __DIR__ . "/../../Models";
        $metaDriver = new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader, $paths);

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setDatabase($database);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setMetadataDriverImpl($metaDriver);
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
        $config->setHttpClient($httpClient);
        $config->setLuceneHandlerName('_fti');

        return \Doctrine\ODM\CouchDB\DocumentManager::create($config);
    }
}