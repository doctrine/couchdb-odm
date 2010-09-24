<?php

namespace Doctrine\Tests\ODM\CouchDB;

class ConfigurationTest extends CouchDBTestCase
{
    public function testHttpClient()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();

        $config->setHttpClient($httpClient);
        $this->assertSame($httpClient, $config->getHttpClient());
    }

    public function testDocumentNamespace()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();

        $config->addDocumentNamespace('foo', 'Documents\Bar');
        $this->assertEquals('Documents\Bar', $config->getDocumentNamespace('foo'));

        $config->setDocumentNamespaces(array('foo' => 'Documents\Bar'));
        $this->assertEquals('Documents\Bar', $config->getDocumentNamespace('foo'));

        $this->setExpectedException('Doctrine\ODM\CouchDB\CouchDBException');
        $config->getDocumentNamespace('bar');
    }
}