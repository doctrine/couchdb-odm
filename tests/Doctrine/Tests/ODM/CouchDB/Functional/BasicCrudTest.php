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
        $this->type = 'Doctrine\Tests\ODM\CouchDB\Functional\User';
        $this->dm = $this->createDocumentManager();

        $httpClient = $this->dm->getConfiguration()->getHttpClient();

        $data = json_encode(
            array(
                '_id' => "1",
                'username' => 'lsmith',
                'doctrine_metadata' => array('type' => $this->type)
            )
        );
        $resp = $httpClient->request('PUT', '/' . $this->dm->getConfiguration()->getDatabase() . '/1', $data);
        $this->assertEquals(201, $resp->status);
    }

    public function testFind()
    {
        $user = $this->dm->find($this->type, 1);

        $this->assertType($this->type, $user);
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

        $userNew = $this->dm->find($this->type, $user->id);

        $this->assertNotNull($userNew, "Have to hydrate user object!");
        $this->assertEquals($user->id, $userNew->id);
        $this->assertEquals($user->username, $userNew->username);
    }

    public function testDelete()
    {
        $user = $this->dm->find($this->type, 1);

        $this->dm->remove($user);
        $this->dm->flush();
        $this->dm->clear();

        $userRemoved = $this->dm->find($this->type, 1);

        $this->assertNull($userRemoved, "Have to delete user object!");
    }

    public function testUpdate1()
    {
        $user = new User();
        $user->id = "myuser-1234";
        $user->username = "test";

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find($this->type, $user->id);
        $user->username = "test2";

        $this->dm->flush();
        $this->dm->clear();

        $userNew = $this->dm->find($this->type, $user->id);

        $this->assertEquals($user->username, $userNew->username);
    }
    
    public function testUpdate2()
    {
        $user = $this->dm->find($this->type, 1);
        $user->username = "new-name";

        $this->dm->flush();
        $this->dm->clear();

        $newUser = $this->dm->find($this->type, 1);
        $this->assertEquals('new-name', $newUser->username);
    }

    public function testRemove()
    {
        $user = $this->dm->find($this->type, 1);

        $this->dm->remove($user);
        $this->dm->flush();

        $newUser = $this->dm->find($this->type, 1);
        $this->assertNull($newUser);
    }

    public function testInsertUpdateMultiple()
    {
        $user1 = $this->dm->find($this->type, 1);
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

        $pUser1 = $this->dm->find($this->type, 1);
        $pUser2 = $this->dm->find($this->type, 'myuser-1111');
        $pUser3 = $this->dm->find($this->type, 'myuser-2222');

        $this->assertEquals('new-name', $pUser1->username);
        $this->assertEquals('myuser-1111', $pUser2->id);
        $this->assertEquals('myuser-2222', $pUser3->id);
    }

    public function testFindTypeValidation()
    {
        $user = $this->dm->find($this->type.'2', 1);
        $this->assertType($this->type, $user);

        $this->dm->getConfiguration()->setValidateDoctrineMetadata(true);
        $user = $this->dm->find($this->type, 1);
        $this->assertType($this->type, $user);

        $this->setExpectedException('InvalidArgumentException');
        $user = $this->dm->find($this->type.'2', 1);
    }
}

/**
 * @Document
 */
class User
{
    /** @Id(strategy="ASSIGNED") */
    public $id;
    /** @String */
    public $username;
}

/**
 * @Document
 */
class User2
{
    /** @Id(strategy="ASSIGNED") */
    public $id;
    /** @String */
    public $username;
}