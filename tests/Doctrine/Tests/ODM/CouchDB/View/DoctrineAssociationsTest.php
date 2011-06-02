<?php

namespace Doctrine\Tests\ODM\CouchDB\View;

use Doctrine\CouchDB\HTTP\SocketClient;
use Doctrine\CouchDB\View\Query;
use Doctrine\ODM\CouchDB\View\DoctrineAssociations;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase;

class DoctrineAssociationsTest extends CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    /**
     * @return NativeQuery
     */
    protected function createDoctrineViewQuery()
    {
        return new Query(
            $this->dm->getHttpClient(),
            $this->dm->getDatabase(),
            'doctrine',
            'inverse_associations',
            new DoctrineAssociations()
        );
    }

    protected function addTestData()
    {
        $db = $this->dm->getHttpClient();

        // Force empty test database
        try {
            $db->request( 'DELETE', '/' . $this->getTestDatabase() . '' );
        } catch ( \Exception $e ) { /* Irrelevant exception */ }
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '' );

        // Create some "interesting" documents
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_a', json_encode( array(
            "_id" => "doc_a",
            "type" => "type_a",
            "doctrine_metadata" => array(
                "associations" => array("type_b", "type_c"),
            ),
            "type_b" => array( "doc_b" ),
            "type_c" => array( "doc_d" ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_b', json_encode( array(
            "_id" => "doc_b",
            "doctrine_metadata" => array(
                "type" => "type_b",
                "associations" => array("type_c")
            ),
            "type_c" => array( "doc_c", "doc_d" ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_c', json_encode( array(
            "_id" => "doc_c",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "associations" => array(),
            ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_d', json_encode( array(
            "_id" => "doc_d",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "associations" => array(),
            ),
        ) ) );
    }

    public function testFetchReverseRelations()
    {
        $this->addTestData();

        $view = $this->createDoctrineViewQuery();
        $result = $view->setStartKey( array("doc_b", "type_b") )
                       ->setEndKey( array("doc_b", "type_b", "z") )
                       ->execute();

        $this->assertEquals(array("_id" => "doc_a"), $result[0]['value']);
    }
    
    public function testFetchReverseRelations1()
    {
        $this->addTestData();

        $view = $this->createDoctrineViewQuery();
        $result = $view->setStartKey( array("doc_d", "type_c") )
                       ->setEndKey(   array("doc_d", "type_c", "z") )
                       ->execute();

        $this->assertEquals(array("_id" => "doc_a"), $result[0]['value']);
        $this->assertEquals(array("_id" => "doc_b"), $result[1]['value']);
    }

    public function testFetchReverseRelations2()
    {
        $this->addTestData();

        $view = $this->createDoctrineViewQuery();
        $result = $view->setStartKey( array("doc_d", "type_b") )
                       ->setEndKey(   array("doc_d", "type_b", "z") )
                       ->execute();

        $this->assertEquals(array(), $result->toArray() );
    }


    public function testFetchNoReverseRelations()
    {
        $this->addTestData();

        $view = $this->createDoctrineViewQuery();
        $result = $view->setStartKey( array("doc_c", "type_a") )
                       ->setEndKey(   array("doc_c", "type_a", "z") )
                       ->execute();

        $this->assertEquals(array(), $result->toArray() );
    }
}

