<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\ODM\CouchDB\UnitOfWork;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class UnitOfWorkTest extends CouchDBTestCase
{
    private $dm;
    private $uow;

    public function setUp()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $this->dm = $config->newDocumentManager();
        $this->uow = new UnitOfWork($this->dm);

        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\ODM\CouchDB\UoWUser');
        $metadata->mapProperty(array('name' => 'id', 'type' => 'string', 'id' => true));
        $metadata->mapProperty(array('name' => 'username', 'type' => 'string'));
        $metadata->idGenerator = \Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_ASSIGNED;

        $cmf = $this->dm->getClassMetadataFactory();
        $cmf->setMetadataFor($metadata);
    }

    public function testCreateDocument()
    {
        $user = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $this->assertType('Doctrine\Tests\ODM\CouchDB\UoWUser', $user);
        $this->assertEquals('1', $user->id);
        $this->assertEquals('foo', $user->username);
        $this->assertEquals(UnitOfWork::STATE_MANAGED, $this->uow->getDocumentState($user));
    }

    public function testCreateDocument_UseIdentityMap()
    {
        $user1 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));
        $user2 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $this->assertSame($user1, $user2);
    }

    public function testTryGetById()
    {
        $user1 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $user2 = $this->uow->tryGetById(1, 'Doctrine\Tests\ODM\CouchDB\UoWUser');

        $this->assertSame($user1, $user2);
    }

    public function testScheduleInsertion()
    {
        $object = new UoWUser();
        $object->id = "1";
        $object->username = "bar";
        
        $this->uow->scheduleInsert($object);
    }

    public function testScheduleInsert_ForAssignedIdGenerator_WithoutId()
    {
        $this->setExpectedException('Exception');

        $object = new UoWUser();
        $object->username = "bar";

        $this->uow->scheduleInsert($object);
    }

    public function testScheduleInsert_ForUuidGenerator_QueriesUuidGenerator()
    {
        $object = new UoWUser();
        $object->username = "bar";

        $this->dm->getClassMetadata(get_class($object))->idGenerator = ClassMetadata::IDGENERATOR_UUID;

        $this->uow->scheduleInsert($object);

        $this->assertNotNull($object->id);
    }

    public function testSCheduleInsert_IdentityMapObject_ThrowsException()
    {
        $user1 = $this->uow->createDocument('Doctrine\Tests\ODM\CouchDB\UoWUser', array('id' => '1', 'username' => 'foo'));

        $this->setExpectedException("Exception");

        $this->uow->scheduleInsert($user1);
    }
}

class UoWUser
{
    public $id;
    public $username;
}