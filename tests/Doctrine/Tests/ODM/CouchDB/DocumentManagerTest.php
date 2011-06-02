<?php

namespace Doctrine\Tests\ODM\CouchDB;

class DocumentManagerTest extends CouchDBTestCase
{
    public function testNewInstanceFromConfiguration()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $httpClient = new \Doctrine\CouchDB\HTTP\SocketClient();
        $couchClient = new \Doctrine\CouchDB\CouchDBClient($httpClient, "test");
        
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($couchClient, $config);

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\DocumentManager', $dm);
        $this->assertSame($config, $dm->getConfiguration());
        $this->assertSame($httpClient, $dm->getHttpClient());
        $this->assertEquals("test", $dm->getDatabase());
    }

    public function testGetClassMetadataFactory()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory', $dm->getClassMetadataFactory());
    }

    public function testGetClassMetadataFor()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));

        $cmf = $dm->getClassMetadataFactory();
        $cmf->setMetadataFor('stdClass', new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass'));

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Mapping\ClassMetadata', $dm->getClassMetadata('stdClass'));
    }

    public function testCreateNewDocumentManagerWithoutHttpClientUsingSocketDefault()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));

        $this->assertInstanceOf('Doctrine\CouchDB\HTTP\SocketClient', $dm->getHttpClient());
    }
}