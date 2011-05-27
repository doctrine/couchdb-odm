<?php

namespace Doctrine\Tests\CouchDB\HTTP;

use Doctrine\CouchDB\HTTP;

class SocketClientTestCase extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * Return test suite
     *
     * @return PHPUnit_Framework_TestSuite
     */
    public static function suite()
    {
        return new \PHPUnit_Framework_TestSuite( __CLASS__ );
    }

    public function testNoConnectionPossible()
    {
        $db = new HTTP\SocketClient( '127.0.0.1', 12345 );

        try {
            $db->request( 'GET', '/' . $this->getTestDatabase() );
            $this->fail( 'Expected HTTPException.' );
        } catch ( HTTP\HTTPException $e ) {
            $this->assertTrue(
                // Message depends on the OS, OSX returns 61, Linux 111
                $e->getMessage() === 'Could not connect to server at 127.0.0.1:12345: \'111: Connection refused\'' ||
                $e->getMessage() === 'Could not connect to server at 127.0.0.1:12345: \'61: Connection refused\''
            );

        }
    }

    public function testCreateDatabase()
    {
        $db = new HTTP\SocketClient();

        // Remove maybe existing database
        try {
            $db->request( 'DELETE', '/' . $this->getTestDatabase() );
        } catch ( \Exception $e ) { /* Irrelevant exception */ }

        $response = $db->request( 'PUT', '/' . $this->getTestDatabase() );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testForErrorOnDatabaseRecreation()
    {
        $db = new HTTP\SocketClient();

        $response = $db->request( 'PUT', '/' . $this->getTestDatabase() );
        $this->assertTrue(
            $response instanceof HTTP\ErrorResponse
        );

        $this->assertSame( 412, $response->status );
        $this->assertSame(
            array( 
                'error'  => 'file_exists',
                'reason' => 'The database could not be created, the file already exists.',
            ),
            $response->body
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testGetDatabaseInformation()
    {
        $db = new HTTP\SocketClient();

        $response = $db->request( 'GET', '/' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            'Welcome',
            $response->body['couchdb']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testAddDocumentToDatabase()
    {
        $db = new HTTP\SocketClient();

        $response = $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testGetAllDocsFormDatabase()
    {
        $db = new HTTP\SocketClient();
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/_all_docs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            1,
            $response->body['total_rows']
        );

        $this->assertSame(
            '123',
            $response->body['rows'][0]['id']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testGetSingleDocumentFromDatabase()
    {
        $db = new HTTP\SocketClient();
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/123' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            '123',
            $response->body['_id']
        );

        $this->assertTrue(
            isset( $response->body['_id'] )
        );

        $this->assertFalse(
            isset( $response->body['unknownProperty'] )
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testGetUnknownDocumentFromDatabase()
    {
        $db = new HTTP\SocketClient();

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/not_existant' );
        $this->assertTrue(
            $response instanceof HTTP\ErrorResponse
        );

        $this->assertSame( 404, $response->status );
        $this->assertSame(
            array( 
                'error'  => 'not_found',
                'reason' => 'missing',
            ),
            $response->body
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testGetDocumentFromNotExistantDatabase()
    {
        $db = new HTTP\SocketClient();

        try
        {
            $db->request( 'DELETE', '/' . $this->getTestDatabase() );
        } catch ( \Exception $e ) { /* Ignore */ }

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/not_existant' );
        $this->assertTrue(
            $response instanceof HTTP\ErrorResponse
        );

        $this->assertSame( 404, $response->status );
        $this->assertSame(
            array( 
                'error'  => 'not_found',
                'reason' => 'no_db_file',
            ),
            $response->body
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testDeleteUnknownDocumentFromDatabase()
    {
        $db = new HTTP\SocketClient();

        $db->request( 'PUT', '/' . $this->getTestDatabase() );
        $response = $db->request( 'DELETE', '/' . $this->getTestDatabase() . '/not_existant' );
        $this->assertTrue(
            $response instanceof HTTP\ErrorResponse
        );

        $this->assertSame( 404, $response->status );
        $this->assertSame(
            array( 
                'error'  => 'not_found',
                'reason' => 'missing',
            ),
            $response->body
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testDeleteSingleDocumentFromDatabase()
    {
        $db = new HTTP\SocketClient();
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/123' );
        $db->request( 'DELETE', '/' . $this->getTestDatabase() . '/123?rev=' . $response->body['_rev'] );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/123' );
        $this->assertTrue(
            $response instanceof HTTP\ErrorResponse
        );

        $this->assertSame( 404, $response->status );
        $this->assertSame(
            array( 
                'error'  => 'not_found',
                'reason' => 'deleted',
            ),
            $response->body
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testDeleteDatabase()
    {
        $db = new HTTP\SocketClient();

        $response = $db->request( 'DELETE', '/' . $this->getTestDatabase() );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testArrayResponse()
    {
        $db = new HTTP\SocketClient();

        $db->request( 'PUT', '/' . $this->getTestDatabase() );
        $response = $db->request( 'GET', '/_all_dbs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertTrue(
            is_array( $response->body )
        );

        $this->assertTrue(in_array(\Doctrine\Tests\ODM\CouchDB\TestUtil::getTestDatabase(), $response->body ));
    }

    /**
     * @depends testCreateDatabase
     */
    public function testCloseConnection()
    {
        $db = new HTTP\SocketClient();
        $db->setOption( 'keep-alive', false );

        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/456', '{"_id":"456","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/789', '{"_id":"789","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/012', '{"_id":"012","data":"Foo"}' );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/_all_docs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            4,
            $response->body['total_rows']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testKeepAliveConnection()
    {
        $db = new HTTP\SocketClient();
        $db->setOption( 'keep-alive', true );

        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/123', '{"_id":"123","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/456', '{"_id":"456","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/789', '{"_id":"789","data":"Foo"}' );
        $db->request( 'PUT', '/' . $this->getTestDatabase() . '/012', '{"_id":"012","data":"Foo"}' );

        $response = $db->request( 'GET', '/' . $this->getTestDatabase() . '/_all_docs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            4,
            $response->body['total_rows']
        );
    }

    /**
     * @depends testCreateDatabase
     */
    public function testUnknownOption()
    {
        $db = new HTTP\SocketClient();

        try {
            $db->setOption( 'unknownOption', 42 );
            $this->fail( 'Expected \InvalidArgumentException' );
        } catch( \InvalidArgumentException $e ) {
            /* Expected */
        }
    }
}

