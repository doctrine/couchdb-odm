<?php

namespace Doctrine\Tests\ODM\CouchDB;

class DocumentManagerTest extends CouchDBTestCase
{
    public function testNewInstanceFromConfiguration()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $this->assertType('Doctrine\ODM\CouchDB\DocumentManager', $dm);
        $this->assertSame($config, $dm->getConfiguration());
    }

    public function testGetClassMetadataFactory()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $this->assertType('Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory', $dm->getClassMetadataFactory());
    }

    public function testGetClassMetadataFor()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $cmf = $dm->getClassMetadataFactory();
        $cmf->setMetadataFor(new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass'));

        $this->assertType('Doctrine\ODM\CouchDB\Mapping\ClassMetadata', $dm->getClassMetadata('stdClass'));
    }

    public function testGetCouchDBClient()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setHttpClient(new \Doctrine\ODM\CouchDB\HTTP\SocketClient());
        $dm = $config->newDocumentManager();

        $client = $dm->getCouchDBClient();

        $this->assertType('Doctrine\ODM\CouchDB\CouchDBClient', $client);
    }

    public function testCreateNewDocumentManagerWithoutHttpClientUsingSocketDefault()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $this->assertType('Doctrine\ODM\CouchDB\HTTP\SocketClient', $dm->getConfiguration()->getHttpClient());
    }
}