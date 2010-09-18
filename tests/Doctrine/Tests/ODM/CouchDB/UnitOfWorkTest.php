<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\ODM\CouchDB\UnitOfWork;

class UnitOfWorkTest extends CouchDBTestCase
{
    private $uow;

    public function setUp()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();
        $this->uow = new UnitOfWork($dm);

        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\ODM\CouchDB\UoWUser');
        $metadata->mapProperty(array('name' => 'id', 'type' => 'string'));
        $metadata->mapProperty(array('name' => 'username', 'type' => 'string'));

        $cmf = $dm->getClassMetadataFactory();
        $cmf->setMetadataFor($metadata);
    }

    public function testCreateDocument()
    {
        $user = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $this->assertType('Doctrine\Tests\ODM\CouchDB\UoWUser', $user);
        $this->assertEquals('1', $user->id);
        $this->assertEquals('foo', $user->username);
    }

    public function testCreateDocument_UseIdentityMap()
    {
        $user1 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));
        $user2 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $this->assertSame($user1, $user2);
    }
}

class UoWUser
{
    public $id;
    public $username;
}