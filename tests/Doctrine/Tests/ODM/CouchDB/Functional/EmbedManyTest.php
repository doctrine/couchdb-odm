<?php
namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Tests\Models\Embedded\Embedded;
use Doctrine\Tests\Models\Embedded\Embedder;
use Doctrine\Tests\Models\Embedded\Nested;

class EmbedManyTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->type = 'Doctrine\Tests\Models\Embedded\Embedder';
        $this->embeddedType = 'Doctrine\Tests\Models\Embedded\Embedded';
        $this->dm = $this->createDocumentManager();

        $document = new Embedder();
        $document->id = 1;
        $embedded1 = new Embedded();
        $embedded1->name = 'embedded 1';
        $embedded2 = new Embedded();
        $embedded2->name = 'embedded 2';

        $document->embeds[] = $embedded1;
        $document->embeds[] = $embedded2;

        $this->dm->persist($document);
        $this->dm->flush();
    }

    public function testFind()
    {
        $embedder = $this->dm->find($this->type, 1);
        $this->assertInstanceOf($this->type, $embedder);
        $this->assertEquals(2, count($embedder->embeds));
        $this->assertEquals('embedded 1', $embedder->embeds[0]->name);
        $this->assertEquals('embedded 2', $embedder->embeds[1]->name);
    }

    public function testShouldNotSaveUnchanged()
    {
        $listener = new PreUpdateSubscriber;
        $this->dm->getEventManager()->addEventListener('preUpdate', $listener);

        $embedder = $this->dm->find($this->type, 1);
        $this->dm->flush();

        $this->assertEquals(0, count($listener->eventArgs));
    }

    public function testSave()
    {
        $embedder = $this->dm->find($this->type, 1);
        // change the first element
        $embedder->embeds[0]->name = 'changed 1';
        // add another one
        $newOne = new Embedded;
        $newOne->name = 'new one';
        $embedder->embeds[] = $newOne;
        $this->dm->flush();
        $this->dm->clear();

        $embedder = $this->dm->find($this->type, 1);
        $this->assertEquals(3, count($embedder->embeds));
        $this->assertEquals('new one', $embedder->embeds[2]->name);

        $embedder->embeds[0]->name = 'changed';
        $embedder->name = 'foo';
        $embedder->embeds[0]->arrayField[] = 'bar';
        $this->dm->flush();
        $this->dm->clear();

        $embedder = $this->dm->find($this->type, 1);
        $this->assertEquals(3, count($embedder->embeds));
        $this->assertEquals('foo', $embedder->name);
        $this->assertEquals('changed', $embedder->embeds[0]->name);
        $this->assertEquals(1, count($embedder->embeds[0]->arrayField));
        $this->assertEquals('bar', $embedder->embeds[0]->arrayField[0]);

    }

    public function testCreate()
    {
        $newOne = new Embedder;
        $newOne->id = '2';

        $embedded1 = new Embedded;
        $embedded1->name = 'newly embedded 1';
        $embedded2 = new Embedded;
        $embedded2->name = 'newly embedded 2';
        $newOne->embeds[] = $embedded1;
        $newOne->embeds[] = $embedded2;

        $this->dm->persist($newOne);
        $this->dm->flush();
        $this->dm->clear();

        $newOne = null;
        $this->assertNull($newOne);
        $newOne = $this->dm->find($this->type, 2);
        $this->assertNotNull($newOne);
        $this->assertEquals(2, count($newOne->embeds));
        $this->assertEquals('newly embedded 1', $newOne->embeds[0]->name);
        $this->assertEquals('newly embedded 2', $newOne->embeds[1]->name);
    }

    public function testAssocCreate()
    {
        $newOne = new Embedder;
        $newOne->id = '2';

        $embedded1 = new Embedded;
        $embedded1->name = 'newly embedded 1';
        $embedded2 = new Embedded;
        $embedded2->name = 'newly embedded 2';
        $newOne->embeds['one'] = $embedded1;
        $newOne->embeds['two'] = $embedded2;

        $this->dm->persist($newOne);
        $this->dm->flush();
        $this->dm->clear();

        $newOne = null;
        $this->assertNull($newOne);
        $newOne = $this->dm->find($this->type, 2);
        $this->assertNotNull($newOne);
        $this->assertEquals(2, count($newOne->embeds));
        $this->assertEquals('newly embedded 1', $newOne->embeds['one']->name);
        $this->assertEquals('newly embedded 2', $newOne->embeds['two']->name);
    }

    public function testMetadataMapping()
    {
        $metadata = $this->dm->getClassMetadata($this->type);
        $this->assertArrayHasKey('embeds', $metadata->fieldMappings);
        $mapping = $metadata->fieldMappings['embeds'];
        $this->assertEquals('mixed', $mapping['type']);
        $this->assertEquals('many', $mapping['embedded']);
        $this->assertEquals($this->embeddedType, $mapping['targetDocument']);
    }

    // TODO testEmbeddedWithNonMappedData
}



class PreUpdateSubscriber implements \Doctrine\Common\EventSubscriber
{
    public $eventArgs = array();
    public function getSubscribedEvents()
    {
        return array(\Doctrine\ODM\CouchDB\Event::preUpdate);
    }

    public function preUpdate(\Doctrine\ODM\CouchDB\Event\LifecycleEventArgs $args)
    {
        $this->eventArgs[] = $args;
    }

}
