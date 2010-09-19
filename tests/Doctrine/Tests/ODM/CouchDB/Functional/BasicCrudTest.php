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

    public function testUpdate()
    {
        $user = $this->dm->find(1);
        $user->username = "new-name";

        $this->dm->flush();
        $this->dm->clear();

        $newUser = $this->dm->find(1);
        $this->assertEquals('new-name', $newUser->username);
    }

    public function testRemove()
    {
        $user = $this->dm->find(1);

        $this->dm->remove($user);
        $this->dm->flush();

        $this->setExpectedException('Doctrine\ODM\CouchDB\DocumentNotFoundException');
        $newUser = $this->dm->find(1);
    }

    public function testInsertUpdateMultiple()
    {
        $user1 = $this->dm->find(1);
        $user1->username = "new-name";

        $user2 = new User();
        $user2->id = "myuser-1111";
        $user2->username = "test";

        $user3 = new User();
        $user3->id = "myuser-2222";
        $user3->username = "test";

        $this->dm->persist($user2);
        $this->dm->persist($user3);
        $this->dm->flush();
        $this->dm->clear();

        $pUser1 = $this->dm->find(1);
        $pUser2 = $this->dm->find('myuser-1111');
        $pUser3 = $this->dm->find('myuser-2222');

        $this->assertEquals('new-name', $pUser1->username);
        $this->assertEquals('myuser-1111', $pUser2->id);
        $this->assertEquals('myuser-2222', $pUser3->id);
    }
}

class User
{
    public $id;
    public $username;
}