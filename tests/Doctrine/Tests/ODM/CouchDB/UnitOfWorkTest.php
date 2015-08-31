<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\ODM\CouchDB\UnitOfWork;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Id\idGenerator;

use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;

class UnitOfWorkTest extends CouchDBTestCase
{
    private $dm;
    private $uow;

    public function setUp()
    {
        $this->type = 'Doctrine\Tests\ODM\CouchDB\UoWUser';
        $this->dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));
        $this->uow = new UnitOfWork($this->dm);

        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata($this->type);
        $metadata->mapField(array('fieldName' => 'id', 'id' => true));
        $metadata->mapField(array('fieldName' => 'username', 'type' => 'string'));
        $metadata->idGenerator = \Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_ASSIGNED;

        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->wakeupReflection(new RuntimeReflectionService());

        $cmf = $this->dm->getClassMetadataFactory();
        $cmf->setMetadataFor($this->type, $metadata);
    }

    public function testCreateDocument()
    {
        $user = $this->uow->createDocument($this->type, array(
            '_id' => '1',
            '_rev' => 23,
            'username' => 'foo',
            'type' => $this->type
        ));

        $this->assertInstanceOf($this->type, $user);
        $this->assertEquals('1', $user->id);
        $this->assertEquals('foo', $user->username);
        $this->assertEquals(UnitOfWork::STATE_MANAGED, $this->uow->getDocumentState($user));
        $this->assertEquals(1, $this->uow->getDocumentIdentifier($user));
        $this->assertEquals(23, $this->uow->getDocumentRevision($user));

        $this->assertEquals(array('id' => '1', 'username' => 'foo'), $this->uow->getOriginalData($user));
    }

    public function testCreateDocument_UseIdentityMap()
    {
        $user1 = $this->uow->createDocument($this->type, array('_id' => '1', '_rev' => 1, 'username' => 'foo', 'type' => $this->type));
        $user2 = $this->uow->createDocument($this->type, array('_id' => '1', '_rev' => 1, 'username' => 'foo', 'type' => $this->type));

        $this->assertSame($user1, $user2);
    }

    public function testTryGetById()
    {
        $user1 = $this->uow->createDocument($this->type, array('_id' => '1', '_rev' => 1, 'username' => 'foo', 'type' => $this->type));

        $user2 = $this->uow->tryGetById(1, $this->type);

        $this->assertSame($user1, $user2);
    }

    public function testScheduleInsertion()
    {
        $httpClient = $this->getMock('Doctrine\CouchDB\HTTP\Client', array(), array(), '', false);
        $httpClient->expects($this->once())
                   ->method('request')
                   ->will($this->returnValue(new \Doctrine\CouchDB\HTTP\Response(404, array(), "{}")));
        $this->dm->getCouchDBClient()->setHttpClient($httpClient);

        $object = new UoWUser();
        $object->id = "1";
        $object->username = "bar";

        $this->uow->scheduleInsert($object);
    }

    public function testScheduleInsert_ForAssignedIdGenerator_WithoutId()
    {
        $this->setExpectedException('Doctrine\ODM\CouchDB\CouchDBException');

        $object = new UoWUser();
        $object->username = "bar";

        $this->uow->scheduleInsert($object);
    }

    public function testScheduleInsert_ForUuidGenerator_QueriesUuidGenerator()
    {
        $uuids = array(
            "4db492fb9e96682601d3f62b0797a8c0",
            "c3cee9c45f2fc2a3803ed26fdbceb3b4",
            "691f868266b6b45a867bfcb4b41a694e",
            "e2c4783e9ff922eefe869998a01828b2"
        );
        $uuidResponse = new \Doctrine\CouchDB\HTTP\Response(200, array(), json_encode(array('uuids' => $uuids)));

        $client = $this->getMock('Doctrine\CouchDB\HTTP\Client');
        $client->expects($this->once())
               ->method('request')
               ->with($this->equalTo('GET'), $this->equalTo('/_uuids?count=20'))
               ->will($this->returnValue($uuidResponse));
        $this->dm->getCouchDBClient()->setHttpClient($client);

        $object = new UoWUser();
        $object->username = "bar";

        $this->dm->getClassMetadata(get_class($object))->idGenerator = ClassMetadata::IDGENERATOR_UUID;
        $this->uow->scheduleInsert($object);

        $this->assertNotNull($object->id);
        $this->assertEquals(end($uuids), $object->id);
    }

    public function testScheduleInsertCancelsScheduleRemove()
    {
        $user1 = $this->uow->createDocument($this->type, array('_id' => '1', '_rev' => 1, 'username' => 'foo', 'type' => $this->type));
        $this->uow->scheduleRemove($user1);

        $this->assertEquals(UnitOfWork::STATE_REMOVED, $this->uow->getDocumentState($user1));

        $this->uow->scheduleInsert($user1);

        $this->assertEquals(UnitOfWork::STATE_MANAGED, $this->uow->getDocumentState($user1));
    }
}

class UoWUser
{
    public $id;
    public $username;
}
