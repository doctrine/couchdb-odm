<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class CouchDBClientTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testGetUuids()
    {
        $uuids = $this->dm->getCouchDBClient()->getUuids();
        $this->assertEquals(1, count($uuids));
        $this->assertEquals(32, strlen($uuids[0]));

        $uuids = $this->dm->getCouchDBClient()->getUuids(10);
        $this->assertEquals(10, count($uuids));
    }

    public function testGetVersion()
    {
        $version = $this->dm->getCouchDBClient()->getVersion();
        $this->assertEquals(3, count(explode(".", $version)));
    }

    public function testGetAllDatabases()
    {
        $dbs = $this->dm->getCouchDBClient()->getAllDatabases();
        $this->assertContains($this->getTestDatabase(), $dbs);
    }
   
    public function testDeleteDatabase()
    {
        $this->dm->getCouchDBClient()->deleteDatabase($this->getTestDatabase());

        $dbs = $this->dm->getCouchDBClient()->getAllDatabases();
        $this->assertNotContains($this->getTestDatabase(), $dbs);
    }

    /**
     * @depends testDeleteDatabase
     */
    public function testCreateDatabase()
    {
        $dbName2 = $this->getTestDatabase() . "2";
        $this->dm->getCouchDBClient()->deleteDatabase($dbName2);
        $this->dm->getCouchDBClient()->createDatabase($dbName2);

        $dbs = $this->dm->getCouchDBClient()->getAllDatabases();
        $this->assertContains($dbName2, $dbs);
    }

    public function testDropMultipleTimesSkips()
    {
        $this->dm->getCouchDBClient()->deleteDatabase($this->getTestDatabase());
        $this->dm->getCouchDBClient()->deleteDatabase($this->getTestDatabase());
    }

    /**
     * @depends testCreateDatabase
     */
    public function testCreateDuplicateDatabaseThrowsException()
    {
        $this->setExpectedException('Doctrine\ODM\CouchDB\HTTP\HTTPException', 'HTTP Error with status 412 occoured while requesting /'.$this->getTestDatabase().'. Error: file_exists The database could not be created, the file already exists.');
        $this->dm->getCouchDBClient()->createDatabase($this->getTestDatabase());
    }

    public function testGetDatabaseInfo()
    {
        $data = $this->dm->getCouchDBClient()->getDatabaseInfo($this->getTestDatabase());

        $this->assertType('array', $data);
        $this->assertArrayHasKey('db_name', $data);
        $this->assertEquals($this->getTestDatabase(), $data['db_name']);
    }

    public function testCreateBulkUpdater()
    {
        $updater = $this->dm->getCouchDBClient()->createBulkUpdater();
        $this->assertType('Doctrine\ODM\CouchDB\Utils\BulkUpdater', $updater);
    }

    /**
     * @depends testCreateBulkUpdater
     */
    public function testGetChanges()
    {
        $updater = $this->dm->getCouchDBClient()->createBulkUpdater();
        $updater->updateDocument(array("_id" => "test1", "foo" => "bar"));
        $updater->updateDocument(array("_id" => "test2", "bar" => "baz"));
        $updater->execute();

        $changes = $this->dm->getCouchDBClient()->getChanges();
        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(2, count($changes['results']));
        $this->assertEquals(2, $changes['last_seq']);
    }

    public function testPostDocument()
    {
        $client = $this->dm->getCouchDBClient();
        list($id, $rev) = $client->postDocument(array("foo" => "bar"));

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "bar"), $response->body);
    }

    public function testPutDocument()
    {
        $id = "foo-bar-baz";
        $client = $this->dm->getCouchDBClient();
        list($id, $rev) = $client->putDocument(array("foo" => "bar"), $id);

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "bar"), $response->body);

        list($id, $rev) = $client->putDocument(array("foo" => "baz"), $id, $rev);

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "baz"), $response->body);
    }

    public function testDeleteDocument()
    {
        $client = $this->dm->getCouchDBClient();
        list($id, $rev) = $client->postDocument(array("foo" => "bar"));

        $client->deleteDocument($id, $rev);

        $response = $client->findDocument($id);
        $this->assertEquals(404, $response->status);
    }
}