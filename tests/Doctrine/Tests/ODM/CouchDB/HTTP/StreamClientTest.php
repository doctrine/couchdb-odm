<?php

namespace Doctrine\Tests\ODM\CouchDB\HTTP;

use Doctrine\ODM\CouchDB\HTTP;

class StreamClientTestCase extends \PHPUnit_Framework_TestCase
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

    /**
     * Check that curl-wrapers are enabled, test cases fail otherwise.
     * 
     * @return void
     */
    public function setUp()
    {
        ob_start();
        phpinfo();
        if ( strpos( ob_get_clean(), 'curlwrappers' ) === false )
        {
            $this->markTestSkipped( 'Enable --with-curlwrappers to run this test.' );
        }
    }

    public function testNoConnectionPossible()
    {

        $db = new HTTP\StreamClient( '127.0.0.1', 12345 );

        try
        {
            $response = $db->request( 'GET', '/doctrine_odm_test' );
            $this->fail( 'Expected HTTP\HTTPException.' );
        }
        catch ( HTTP\HTTPException $e )
        {
            $this->assertTrue(
                // Message depends on whether the internal stream wrapper or 
                // the curlwrappers are used
                $e->getMessage() === 'Could not connect to server at 127.0.0.1:12345: \'0: fopen(http://127.0.0.1:12345/doctrine_odm_test): failed to open stream: operation failed\'' ||
                $e->getMessage() === 'Could not connect to server at 127.0.0.1:12345: \'0: fopen(http://127.0.0.1:12345/doctrine_odm_test): failed to open stream: Connection refused\''
            );
        }
    }

    public function testCreateDatabase()
    {
        $db = new HTTP\StreamClient();

        // Remove maybe existing database
        try {
            $response = $db->request( 'DELETE', '/doctrine_odm_test' );
        } catch ( \Exception $e ) { /* Irrelevant exception */ }

        $response = $db->request( 'PUT', '/doctrine_odm_test' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    public function testForErrorOnDatabaseRecreation()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'PUT', '/doctrine_odm_test' );
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

    public function testGetDatabaseInformation()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'GET', '/' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            'Welcome',
            $response->body['couchdb']
        );
    }

    public function testAddDocumentToDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    public function testGetAllDocsFormDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );
        $response = $db->request( 'GET', '/doctrine_odm_test/_all_docs' );

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

    public function testGetSingleDocumentFromDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );
        $response = $db->request( 'GET', '/doctrine_odm_test/123' );

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

    public function testGetUnknownDocumentFromDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'GET', '/doctrine_odm_test/not_existant' );
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

    public function testGetDocumentFromNotExistantDatabase()
    {
        $db = new HTTP\StreamClient();

        try {
            $response = $db->request( 'DELETE', '/doctrine_odm_test' );
        } catch ( \Exception $e ) { /* Ignore */ }

        $response = $db->request( 'GET', '/doctrine_odm_test/not_existant' );
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

    public function testDeleteUnknownDocumentFromDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'DELETE', '/doctrine_odm_test/not_existant' );
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

    public function testDeleteSingleDocumentFromDatabase()
    {
        $db = new HTTP\StreamClient();

        $db->request( 'PUT', '/doctrine_odm_test' );
        $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );
        $response = $db->request( 'GET', '/doctrine_odm_test/123' );
        $db->request( 'DELETE', '/doctrine_odm_test/123?rev=' . $response->body['_rev'] );

        $response = $db->request( 'GET', '/doctrine_odm_test/123' );
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

    public function testDeleteDatabase()
    {
        $db = new HTTP\StreamClient();

        $response = $db->request( 'DELETE', '/doctrine_odm_test' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            true,
            $response->body['ok']
        );
    }

    public function testArrayResponse()
    {
        $db = new HTTP\StreamClient();

        try {
            $db->request( 'PUT', '/doctrine_odm_test' );
        } catch ( \Exception $e ) { /* Ignore */ }
        $response = $db->request( 'GET', '/_all_dbs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertTrue(
            is_array( $response->body )
        );

        $this->assertTrue(
            in_array( 'doctrine_odm_test', $response->body )
        );
    }

    public function testCloseConnection()
    {
        $db = new HTTP\StreamClient();
        $db->setOption( 'keep-alive', false );

        $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/456', '{"_id":"456","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/789', '{"_id":"789","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/012', '{"_id":"012","data":"Foo"}' );

        $response = $db->request( 'GET', '/doctrine_odm_test/_all_docs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            4,
            $response->body['total_rows']
        );
    }

    public function testKeepAliveConnection()
    {
        $db = new HTTP\StreamClient();
        $db->setOption( 'keep-alive', true );

        $db->request( 'PUT', '/doctrine_odm_test/123', '{"_id":"123","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/456', '{"_id":"456","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/789', '{"_id":"789","data":"Foo"}' );
        $db->request( 'PUT', '/doctrine_odm_test/012', '{"_id":"012","data":"Foo"}' );

        $response = $db->request( 'GET', '/doctrine_odm_test/_all_docs' );

        $this->assertTrue(
            $response instanceof HTTP\Response
        );

        $this->assertSame(
            4,
            $response->body['total_rows']
        );
    }

    public function testUnknownOption()
    {
        $db = new HTTP\StreamClient();

        try
        {
            $db->setOption( 'unknownOption', 42 );
            $this->fail( 'Expected \InvalidArgumentException.' );
        }
        catch( \InvalidArgumentException $e )
        { /* Expected */ }
    }
}

