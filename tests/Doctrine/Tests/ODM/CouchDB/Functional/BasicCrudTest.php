<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class BasicCrudTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();
        
        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);
        $this->assertEquals(201, $resp->status);

        $data = json_encode(
                array('_id' => "1", 'username' => 'lsmith', 'doctrine_metadata' => array('type' => 'Doctrine\Tests\ODM\CouchDB\Functional\User')));
        $resp = $httpClient->request('PUT', '/' . $database . '/1', $data);
        $this->assertEquals(201, $resp->status);

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setHttpClient($httpClient);
        $config->setDatabaseName($database);

        $this->dm = $config->newDocumentManager();

        $cmf = $this->dm->getClassMetadataFactory();
        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\ODM\CouchDB\Functional\User');
        $metadata->mapProperty(array('name' => 'id', 'type' => 'string', 'id' => true, 'resultkey' => '_id'));
        $metadata->mapProperty(array('name' => 'username', 'type' => 'string'));
        $metadata->idGenerator = \Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_ASSIGNED;
        $cmf->setMetadataFor($metadata);
    }

    public function testFindById()
    {
        $user = $this->dm->find(1);

        $this->assertType('Doctrine\Tests\ODM\CouchDB\Functional\User', $user);
        $this->assertEquals('1', $user->id);
        $this->assertEquals('lsmith', $user->username);
    }

    public function testInsert()
    {
        $user = new User();
        $user->id = "myuser-1234";
        $user->username = "test";

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $userNew = $this->dm->find($user->id);

        $this->assertNotNull($userNew, "Have to hydrate user object!");
        $this->assertEquals($user->id, $userNew->id);
        $this->assertEquals($user->username, $userNew->username);
    }
}

class User
{
    public $id;
    public $username;
}