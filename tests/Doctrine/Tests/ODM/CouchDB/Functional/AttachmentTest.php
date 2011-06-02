<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\CouchDB\Attachment;

class AttachmentTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
        $client = $this->dm->getHttpClient();
        $response = $client->request('PUT', '/' . $this->getTestDatabase() . '/user_with_attachment', \file_get_contents(__DIR__ . "/_files/user_with_attachment.json"));
    }

    public function testHydrateAttachments()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');

        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $user, 'User not hydrated correctly!');
        $this->assertInternalType('array', $user->attachments, "Attachments are always an array.");
        $this->assertArrayHasKey('foo.txt', $user->attachments);
        $this->assertInstanceOf('Doctrine\CouchDB\Attachment', $user->attachments['foo.txt']);
        $this->assertFalse($user->attachments['foo.txt']->isLoaded());
        $this->assertEquals('This is a base64 encoded text', $user->attachments['foo.txt']->getRawData());
    }

    public function testPersistUnchangedAttachmentCorrectly()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $user->username = "newusername!";

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $this->assertArrayHasKey('foo.txt', $user->attachments);
        $this->assertInstanceOf('Doctrine\CouchDB\Attachment', $user->attachments['foo.txt']);
    }

    public function testRemoveAttachment()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        unset($user->attachments['foo.txt']);

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $this->assertEquals(0, count($user->attachments));
    }

    public function testAddAttachment()
    {
        $fh = fopen(__DIR__ . '/_files/logo.jpg', 'r');

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $user->attachments['logo.jpg'] = Attachment::createFromBinaryData($fh, 'image/jpeg');

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');

        $this->assertEquals(2, count($user->attachments));
        $this->assertArrayHasKey('logo.jpg', $user->attachments);
        $this->assertInstanceOf('Doctrine\CouchDB\Attachment', $user->attachments['foo.txt']);
    }

    public function testUpdateAttachment()
    {
        $fh = fopen(__DIR__ . '/_files/foo.txt', 'r');

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $user->attachments['foo.txt'] = Attachment::createFromBinaryData($fh, 'text/plain');

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $this->assertEquals('Hello i am a string!', $user->attachments['foo.txt']->getRawData());
    }

    public function testAddRemoveAttachment()
    {
        $fh = fopen(__DIR__ . '/_files/logo.jpg', 'r');

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $user->attachments['logo.jpg'] = Attachment::createFromBinaryData($fh, 'image/jpeg');

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        unset($user->attachments['foo.txt']);

        $this->dm->flush();
        $this->dm->clear(); // dont re-use identity map

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $this->assertArrayHasKey('logo.jpg', $user->attachments);
        $this->assertArrayNotHasKey('foo.txt', $user->attachments);
    }
}