<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\CouchDB\Attachment;

class AttachmentTest extends CouchDBTestCase
{
    public function testCreateFromBinaryData()
    {
        $text = "Hello i am a string";
        $attachment = Attachment::createFromBinaryData($text, "text/plain");

        $this->assertEquals($text, $attachment->getRawData());
        $this->assertEquals("text/plain", $attachment->getContentType());
        $this->assertEquals("SGVsbG8gaSBhbSBhIHN0cmluZw==", $attachment->getBase64EncodedData());
        $this->assertEquals(28, $attachment->getLength());
    }

    public function testCreateFromBase64Data()
    {
        $data = "SGVsbG8gaSBhbSBhIHN0cmluZw==";

        $attachment = Attachment::createFromBase64Data($data, "text/plain", 2);

        $this->assertEquals("Hello i am a string", $attachment->getRawData());
        $this->assertEquals($data, $attachment->getBase64EncodedData());
        $this->assertEquals("text/plain", $attachment->getContentType());
        $this->assertEquals(28, $attachment->getLength());
        $this->assertEquals(2, $attachment->getRevPos());
    }

    public function testCreateStub()
    {
        $httpClient = $this->getMock('Doctrine\CouchDB\HTTP\Client');
        $httpClient->expects($this->never())->method('request');
        $attachment = Attachment::createStub('plain/text', 28, 2, $httpClient, '/');

        $this->assertEquals('plain/text', $attachment->getContentType());
        $this->assertEquals(28, $attachment->getLength());
        $this->assertEquals(2, $attachment->getRevPos());
        $this->assertFalse($attachment->isLoaded());
    }

    public function testTriggerStubLazyLoad()
    {
        $path = '/';

        $response = new \Doctrine\CouchDB\HTTP\Response(200, array(), 'Hello i am a string', true);
        $httpClient = $this->getMock('Doctrine\CouchDB\HTTP\Client');
        $httpClient->expects($this->once())
                   ->method('request')
                   ->with($this->equalTo('GET'), $this->equalTo($path))
                   ->will($this->returnValue( $response ));
        $attachment = Attachment::createStub('plain/text', 28, 2, $httpClient, $path);

        $this->assertFalse($attachment->isLoaded());
        $this->assertEquals('Hello i am a string', $attachment->getRawData());
        $this->assertEquals('SGVsbG8gaSBhbSBhIHN0cmluZw==', $attachment->getBase64EncodedData());
        $this->assertTrue($attachment->isLoaded());
    }

    public function testTriggerLazyLoadOfMissingAttachmentThrowsException()
    {
        $path = '/';

        $errorResponse = new \Doctrine\CouchDB\HTTP\ErrorResponse(404, array(), '{"error":"not_found","reason":"missing"}');
        $httpClient = $this->getMock('Doctrine\CouchDB\HTTP\Client');
        $httpClient->expects($this->once())
                   ->method('request')
                   ->with($this->equalTo('GET'), $this->equalTo($path))
                   ->will($this->returnValue( $errorResponse ));
        $attachment = Attachment::createStub('plain/text', 28, 2, $httpClient, $path);

        $this->setExpectedException('Doctrine\CouchDB\HTTP\HTTPException');
        $attachment->getRawData();
    }

    public function testToArray()
    {
        $text = "Hello i am a string";
        $attachment = Attachment::createFromBinaryData($text, "text/plain");

        $this->assertEquals(array("data" => "SGVsbG8gaSBhbSBhIHN0cmluZw==", "content_type" => "text/plain"), $attachment->toArray());
    }

    public function testToArrayStub()
    {
        $httpClient = $this->getMock('Doctrine\CouchDB\HTTP\Client');
        $httpClient->expects($this->never())->method('request');
        $attachment = Attachment::createStub('plain/text', 28, 2, $httpClient, '/');

        $this->assertEquals(array('stub' => true), $attachment->toArray());
    }

    public function testCreateFromBinaryFileHandle()
    {
        $fh = fopen(__DIR__ . "/_files/foo.txt", "r");

        $attachment = Attachment::createFromBinaryData($fh);
        $this->assertEquals('Hello i am a string!', $attachment->getRawData());
    }
}