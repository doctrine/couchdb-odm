<?php
namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\ODM\CouchDB\Migrations\DocumentMigration;

class MigrationsTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    public function createConfiguration($metaDriver)
    {
        $config = parent::createConfiguration($metaDriver);

        $config->setMigrations(new TestMigration());

        return $config;
    }

    public function testMigrate()
    {
        $documentManager = $this->createDocumentManager();
        $client = $documentManager->getCouchDBClient();

        $client->putDocument(array('foo' => 'bar'), 'foo'); 

        $document = $documentManager->find(__NAMESPACE__ . '\\MigrateDocument', 'foo');

        $this->assertInstanceOf(__NAMESPACE__ . '\\MigrateDocument', $document);
        $this->assertEquals('bar', $document->name);
    }
}

class TestMigration implements DocumentMigration
{
    public function migrate(array $data)
    {
        $data['type'] = str_replace('\\', '.', __NAMESPACE__ . '\\MigrateDocument');
        $data['doctrine_metadata'] = array();

        $data['name'] = $data['foo'];
        unset($data['foo']);

        return $data;
    }
}

/**
 * @Document
 */
class MigrateDocument
{
    /**
     * @Id
     */
    public $id;

    /**
     * @Field(type="string")
     */
    public $name;
}
