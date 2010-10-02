<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\SocketClient;
use Doctrine\ODM\CouchDB\View\Relations;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class RelationsTest extends CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setDatabase( $this->getTestDatabase() );

        $this->dm = \Doctrine\ODM\CouchDB\DocumentManager::create(new SocketClient(), $config);
    }

    protected function addTestData()
    {
        $db = $this->dm->getConfiguration()->getHttpClient();

        // Force empty test database
        try {
            $db->request( 'DELETE', '/' . $this->getTestDatabase() . '' );
        } catch ( \Exception $e ) { /* Irrelevant exception */ }
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '' );

        // Create some "interesting" documents
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_a', json_encode( array(
            "_id" => "doc_a",
            "doctrine_metadata" => array(
                "type" => "type_a",
                "relations" => array(
                    "type_b" => array( "doc_b" ),
                    "type_c" => array( "doc_d" ),
                ),
            ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_b', json_encode( array(
            "_id" => "doc_b",
            "doctrine_metadata" => array(
                "type" => "type_b",
                "relations" => array(
                    "type_c" => array( "doc_c", "doc_d" ),
                ),
            ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_c', json_encode( array(
            "_id" => "doc_c",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "relations" => array(),
            ),
        ) ) );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/doc_d', json_encode( array(
            "_id" => "doc_d",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "relations" => array(),
            ),
        ) ) );
    }

    public function testCreateView()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_b"
                ),
            ),
            $view->getRelatedObjects( "doc_a", "type_b" )
        );
    }

    public function testRefetchView()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_c"
                ),
                array(
                    "_id" => "doc_d"
                ),
            ),
            $view->getRelatedObjects( "doc_b", "type_c" )
        );
    }

    public function testFetchReverseRelations()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_a"
                ),
            ),
            $view->getReverseRelatedObjects( "doc_d", "type_a" )
        );
    }

    public function testFetchNoReverseRelations()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(),
            $view->getReverseRelatedObjects( "doc_c", "type_a" )
        );
    }
}

