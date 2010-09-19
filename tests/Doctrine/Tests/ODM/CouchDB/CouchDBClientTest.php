<?php

namespace Doctrine\Tests\ODM\CouchDB;

class CouchDBClientTest extends CouchDBTestCase
{
    private $httpClient;
    private $database;
    private $client;

    public function setUp()
    {
        $this->httpClient = $this->getMock('Doctrine\ODM\CouchDB\HTTP\Client', array('request'));
        $this->database = 'doctrine_mock_testdb';

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setDatabaseName($this->database);
        $config->setHttpClient($this->httpClient);
        
        $this->client = new \Doctrine\ODM\CouchDB\CouchDBClient($config);
    }

    public function testGenerateUUIDs()
    {
        $this->httpClient->expects($this->once())
                         ->method('request')
                         ->with($this->equalTo('GET'), $this->equalTo('/_uuids?count=42'))
                         ->will($this->returnValue(new \Doctrine\ODM\CouchDB\HTTP\Response(200, array(), json_encode(array('uuids' => array(1, 2, 3, 4))))));

        $uuids = $this->client->getUuids(42);

        $this->assertEquals(array(1, 2, 3, 4), $uuids);
    }


}
