<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional\Mapping;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\Tests\Models\Mapping\HeadlineArticle;
use Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase;
use Doctrine\Tests\Models\Mapping\ExtendingClass;

/**
 * Test about mapped superclass and about extending a base document
 */
class DocumentInheritTest extends CouchDBFunctionalTestCase
{
    /** @var DocumentManager */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testLoadingParentClass()
    {
        $document = new HeadlineArticle();
        $document->topic = 'Superclass test';
        $document->headline = 'test test test';
        $this->dm->persist($document);
        $this->dm->flush();
        $id = $document->id;

        $this->dm->clear();

        $doc = $this->dm->find('Doctrine\Tests\Models\Mapping\HeadlineArticle', $id);
        $this->assertInstanceOf('\Doctrine\Tests\Models\Mapping\HeadlineArticle', $doc);
        $this->assertEquals('test test test', $doc->headline);
        $this->assertEquals('Superclass test', $doc->topic);
    }

    public function testLoadingMappedsuperclass()
    {
        $document = new ExtendingClass();
        $document->topic = 'Superclass test';
        $document->headline = 'test test test';
        $this->dm->persist($document);
        $this->dm->flush();
        $id = $document->id;

        $this->dm->clear();

        $doc = $this->dm->find('Doctrine\Tests\Models\Mapping\ExtendingClass', $id);
        $this->assertInstanceOf('\Doctrine\Tests\Models\Mapping\ExtendingClass', $doc);
        $this->assertEquals('test test test', $doc->headline);
        $this->assertEquals('Superclass test', $doc->topic);
    }
}
