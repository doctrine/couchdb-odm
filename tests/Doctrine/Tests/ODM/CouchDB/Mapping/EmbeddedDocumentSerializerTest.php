<?php


namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\CouchDB\Mapping\EmbeddedDocumentSerializer;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
use Doctrine\ODM\CouchDB\Mapping\MetadataResolver\DoctrineResolver;
/*
    public function __constrct(DocumentManager $dm)
    public function serializeEmbeddedDocument($embeddedValue, $embeddedFieldMapping)
    public function createEmbeddedDocument($data, $embeddedFieldMapping)
    public function compare($jsonValue, $embeddedDocument)
*/

class EmbeddedDocumentSerializerTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $serializer;
    public function setUp()
    {
        $dm = $this->createDocumentManager();
        $this->metadataFactory = new ClassMetadataFactory($dm);
        $resolver = new DoctrineResolver();
        $this->serializer = new EmbeddedDocumentSerializer($this->metadataFactory, $resolver);

        $this->arrayDataFixture = array(
            'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Embedded',
            'name' => 'embedded-1',
            'embeds' => array(
                'one' => array(
                    'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Nested',
                    'nestedName' => 'a111'
                    ),
                'two' => array(
                    'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Nested',
                    'nestedName' => 'a222'
                    )
                ),
            );
        $this->embedOneFixture = array(
                'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Embedded',
                'name' => 'embeddedAnyOne',

            );
        $this->embedAnyFixture = array(
            'any_1' => array(
                'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Embedded',
                'name' => 'embedAny_1'
                ),
            'any_2' => array(
                'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Nested',
                'nestedName' => 'embedAny_2'
                )
            );
    }

    public function testCreateEmbeddedDocument()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $embedderMetadata->fieldMappings['embedded']);

        $this->assertNotNull($instance);
        $this->assertEquals('embedded-1', $instance->name);
        $this->assertTrue($instance->embeds->containsKey('one'));
        $this->assertTrue($instance->embeds->containsKey('two'));
        $this->assertEquals(2, count($instance->embeds));
    }

    public function testCreateEmbeddedNoTargetDocument()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $instance = $this->serializer->createEmbeddedDocument(
            $this->embedOneFixture,
            $embedderMetadata->fieldMappings['embedAnyOne']);
        $this->assertInstanceOf('Doctrine\Tests\ODM\CouchDB\Mapping\Embedded', $instance);
        $this->assertEquals('embeddedAnyOne', $instance->name);

        $instance = $this->serializer->createEmbeddedDocument(
            $this->embedAnyFixture,
            $embedderMetadata->fieldMappings['embedAny']);
        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection',$instance);
        $this->assertEquals(2, count($instance));
        $this->assertTrue($instance->containsKey('any_1'));
        $this->assertTrue($instance->containsKey('any_2'));
        $this->assertInstanceOf('Doctrine\Tests\ODM\CouchDB\Mapping\Embedded', $instance['any_1']);
        $this->assertInstanceOf('Doctrine\Tests\ODM\CouchDB\Mapping\Nested', $instance['any_2']);
        $this->assertEquals('embedAny_1', $instance['any_1']->name);
        $this->assertEquals('embedAny_2', $instance['any_2']->nestedName);
    }

    /**
     * If there is no doctrine_metadata inside of an embedded array, classmetadata should help
     */
    public function testCreateNoMetadata()
    {
        unset($this->arrayDataFixture['doctrine_metadata']);

        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $embedderMetadata->fieldMappings['embedded']);
        $this->assertNotNull($instance);
        $this->assertEquals('embedded-1', $instance->name);
        $this->assertTrue($instance->embeds->containsKey('one'));
        $this->assertTrue($instance->embeds->containsKey('two'));
        $this->assertEquals(2, count($instance->embeds));
    }

    public function testCreateThorwsWhenNoTargetNoMetadata()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $this->arrayDataFixture['embedAny'] = $this->embedAnyFixture;
        unset($this->arrayDataFixture['embedAny']['any_2']['doctrine_metadata']);

        $this->setExpectedException('InvalidArgumentException');
        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $embedderMetadata->fieldMappings['embedAny']);

    }

    public function testCreateMetadataConflict()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $this->arrayDataFixture['embeds']['two']['type'] = 'Doctrine.Tests.ODM.CouchDB.Mapping.Embedded';
        $this->setExpectedException('InvalidArgumentException');
        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $embedderMetadata->fieldMappings['embedAny']);
    }

    public function testSerialize()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $embedder = new Embedder;
        $embedder->id = 'embedder-1';

        $embedded = new Embedded;
        $embedded->name = 'embedded-1';

        $nested1 = new Nested;
        $nested1->nestedName = 'a111';
        $embedded->embeds['one'] = $nested1;
        $nested2 = new Nested;
        $nested2->nestedName = 'a222';
        $embedded->embeds['two'] = $nested2;

        $embedder->embedded = $embedded;
        $arrayData = $this->serializer->serializeEmbeddedDocument(
            $embedded,
            $embedderMetadata->fieldMappings['embedded']);

        $this->assertEquals($this->arrayDataFixture, $arrayData);
    }

    public function testSerializeMismatchingTargetDocument()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $embedder = new Embedder;
        $embedder->id = 'embedder-1';

        $embedded = new Embedded;
        $embedded->name = 'embedded-1';

        $nested1 = new Nested;
        $nested1->nestedName = 'a111';
        $embedded->embeds['one'] = $nested1;
        $nested2 = new Embedded;
        $nested2->name = 'a222';
        $embedded->embeds['two'] = $nested2;

        $embedder->embedded = $embedded;

        $this->setExpectedException('InvalidArgumentException');
        $arrayData = $this->serializer->serializeEmbeddedDocument(
            $embedded,
            $embedderMetadata->fieldMappings['embedded']);

        $this->assertEquals($this->arrayDataFixture, $arrayData);

    }

    public function testSerializeNoTargetDocument()
    {
        $embedder = new Embedder;
        $embeddedMany_1 = new Embedded;
        $embeddedMany_1->name = 'embeddedMany_1';
        $embeddedMany_2 = new Nested;
        $embeddedMany_2->nestedName = 'embeddedMany_2';

        $embedder->embedAny = array($embeddedMany_1, $embeddedMany_2);

        $embedOne = new Nested;
        $embedOne->nestedName = 'embedOne';
        $embedder->embedAnyOne = $embedOne;

        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $arrayData = $this->serializer->serializeEmbeddedDocument(
            $embedder->embedAny,
            $embedderMetadata->fieldMappings['embedAny']);

        $this->assertEquals(
            array(
                array(
                    'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Embedded',
                    'name' => 'embeddedMany_1',
                    'embeds' => array(),
                    ),
                array(
                    'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Nested',
                    'nestedName' => 'embeddedMany_2'
                    )
                ),
            $arrayData);

        $arrayData = $this->serializer->serializeEmbeddedDocument(
            $embedder->embedAnyOne,
            $embedderMetadata->fieldMappings['embedAnyOne']);
        $this->assertEquals(
            array(
                'type' => 'Doctrine.Tests.ODM.CouchDB.Mapping.Nested',
                'nestedName' => 'embedOne'
                ),
            $arrayData);
    }

    public function testIsChanged()
    {
        $embedderMetadata =
            $this->metadataFactory->getMetadataFor('Doctrine\Tests\ODM\CouchDB\Mapping\Embedder');

        $fieldMapping = $embedderMetadata->fieldMappings['embedded'];
        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $fieldMapping);

        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        $name = $instance->name;
        $instance->name = 'changed';
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));
        $instance->name = $name;
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));


        $nestedName = $instance->embeds['one']->nestedName;
        $instance->embeds['one']->nestedName = 'changed';
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));
        $instance->embeds['one']->nestedName = $nestedName;
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        // testing same data representation but different class
        $newNested = new Nested2;
        $newNested->nestedName = $nestedName;
        $instance->embeds['one'] = $newNested;
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        $newNested = new Nested;
        $newNested->nestedName = $nestedName;
        $instance->embeds['one'] = $newNested;
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        // testing that to null a property is a change
        $instance->embeds['one']->nestedName = null;
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        $instance->embeds['one']->nestedName = $nestedName;
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        // adding a new value is a change
        $instance->embeds['three'] = new Nested;
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));
        unset($instance->embeds['three']);
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        // removing one is a change
        unset($instance->embeds['two']);
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        // same number of embeds, but different key
        $instance->embeds['1'] = new Nested;
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));


        // -----------------------------------------------------------------------------
        //  reset things
        // -----------------------------------------------------------------------------
        $instance = $this->serializer->createEmbeddedDocument(
            $this->arrayDataFixture,
            $embedderMetadata->fieldMappings['embedded']);
        $this->assertFalse($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));


        // adding an EmbedOne type of embedded document
        // also tesing the no targetDocument case
        $instance->embedOne = new Nested;
        $this->assertTrue($this->serializer->isChanged($instance, $this->arrayDataFixture, $fieldMapping));

        $fixture = $this->arrayDataFixture;
        $fixture['embedOne'] = array('doctrine_metadata'=>array('type'=>'Doctrine.Tests.ODM.CouchDB.Mapping.Nested'));
        $this->assertFalse($this->serializer->isChanged($instance, $fixture, $fieldMapping));

        $instance->embedOne->nestedName = 'so this is nested';
        $this->assertTrue($this->serializer->isChanged($instance, $fixture, $fieldMapping));
    }
}




/**
 * @Document
 */
class Embedder {
    /**
     * @Id(strategy="ASSIGNED")
     */
    public $id;

    /**
     * @EmbedOne(targetDocument="Doctrine\Tests\ODM\CouchDB\Mapping\Embedded")
     */
    public $embedded;

    /**
     * @EmbedOne
     */
    public $embedAnyOne;

    /**
     * @EmbedMany
     */
    public $embedAny = array();
}

/**
 * @EmbeddedDocument
 */
class Embedded {
    /**
     * @Field
     */
    public $name;

    /**
     * @EmbedMany(targetDocument="Doctrine\Tests\ODM\CouchDB\Mapping\Nested")
     */
    public $embeds = array();

    /**
     * @EmbedOne
     */
    public $embedOne;
}

/**
 * @EmbeddedDocument
 */
class Nested {
    /**
     * @Field
     */
    public $nestedName;
}

/**
 * @EmbeddedDocument
 */
class Nested2 extends Nested {
    /**
     * @Field
     */
    public $nestedName;
}
