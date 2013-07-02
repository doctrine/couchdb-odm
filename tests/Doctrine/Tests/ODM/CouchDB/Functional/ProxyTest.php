<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;

class ProxyTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->type = 'Doctrine\Tests\ODM\CouchDB\Functional\Article';

        $this->dm = $this->createDocumentManager();

        $database = $this->dm->getDatabase();
        $httpClient = $this->dm->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);
        $this->assertEquals(201, $resp->status);

        $data = json_encode(array(
            '_id' => "1",
            'title' => 'foo',
            'body' => 'bar',
            'type' => $this->type
        ));
        $resp = $httpClient->request('PUT', '/' . $database . '/1', $data);
        $this->assertEquals(201, $resp->status);

        $cmf = $this->dm->getClassMetadataFactory();
        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata($this->type);
        $metadata->mapField(array('fieldName' => 'id', 'id' => true));
        $metadata->mapField(array('fieldName' => 'title', 'type' => 'string'));
        $metadata->mapField(array('fieldName' => 'body', 'type' => 'string'));
        $metadata->idGenerator = \Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_UUID;
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->wakeupReflection(new RuntimeReflectionService());
        $cmf->setMetadataFor($this->type, $metadata);
    }

    public function testGetReference()
    {
        $proxy = $this->dm->getReference($this->type, 1);

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Proxy\Proxy', $proxy);
        $this->assertFalse($proxy->__isInitialized__);

        $this->assertEquals('foo', $proxy->getTitle());
        $this->assertTrue($proxy->__isInitialized__);
        $this->assertEquals('bar', $proxy->getBody());
    }

    public function testProxyFactorySetsProxyMetadata()
    {
        $proxy = $this->dm->getReference($this->type, 1);

        $proxyClass = get_class($proxy);
        $this->assertTrue($this->dm->getClassMetadataFactory()->hasMetadataFor($proxyClass), "Proxy class '" . $proxyClass . "' should be registered as metadata.");
        $this->assertSame($this->dm->getClassMetadata($proxyClass), $this->dm->getClassMetadata($this->type), "Metadata instances of proxy class and real instance have to be the same.");
    }
}

class Article
{
    private $id;
    private $title;
    private $body;

    public function __construct($title, $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getBody()
    {
        return $this->body;
    }
}
