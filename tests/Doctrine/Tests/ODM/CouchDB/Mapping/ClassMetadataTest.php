<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;

class ClassMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testClassName()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");

        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $cm->name);

        return $cm;
    }

    /**
     * @depends testClassName
     */
    public function testMapFieldWithId($cm)
    {
        $cm->mapField(array('fieldName' => 'id', 'id' => true));

        $this->assertTrue(isset($cm->fieldMappings['id']));
        $this->assertEquals(array('jsonName' => '_id', 'id' => true, 'type' => 'string', 'fieldName' => 'id'), $cm->fieldMappings['id']);

        $this->assertEquals('id', $cm->identifier);
        $this->assertEquals(array('_id' => 'id'), $cm->jsonNames);

        return $cm;
    }

    /**
     * @depends testMapFieldWithId
     */
    public function testMapField($cm)
    {
        $cm->mapField(array('fieldName' => 'username', 'type' => 'string'));
        $cm->mapField(array('fieldName' => 'created', 'type' => 'datetime'));

        $this->assertTrue(isset($cm->fieldMappings['username']));
        $this->assertTrue(isset($cm->fieldMappings['created']));

        $this->assertEquals(array('jsonName' => 'username', 'type' => 'string', 'fieldName' => 'username'), $cm->fieldMappings['username']);
        $this->assertEquals(array('jsonName' => 'created', 'type' => 'datetime', 'fieldName' => 'created'), $cm->fieldMappings['created']);

        $this->assertEquals(array('_id' => 'id', 'username' => 'username', 'created' => 'created'), $cm->jsonNames);

        return $cm;
    }

    /**
     * @depends testMapField
     */
    public function testmapFieldWithoutNameThrowsException($cm)
    {
        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');

        $cm->mapField(array());
    }

    /**
     * @depends testMapField
     */
    public function testNewInstance($cm)
    {
        $instance1 = $cm->newInstance();
        $instance2 = $cm->newInstance();

        $this->assertInstanceOf('Doctrine\Tests\ODM\CouchDB\Mapping\Person', $instance1);
        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * @depends testMapField
     */
    public function testMapVersionField($cm)
    {
        $this->assertFalse($cm->isVersioned);
        $cm->mapField(array('fieldName' => 'version', 'jsonName' => '_rev', 'isVersionField' => true));

        $this->assertTrue($cm->isVersioned);
        $this->assertEquals('version', $cm->versionField);
    }

    public function testMapFieldWithoutType_DefaultsToMixed()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");

        $cm->mapField(array('fieldName' => 'username'));

        $this->assertEquals(array('jsonName' => 'username', 'type' => 'mixed', 'fieldName' => 'username'), $cm->fieldMappings['username']);
    }

    /**
     * @param ClassMetadata $cm
     * @depends testClassName
     */
    public function testMapAssociationManyToOne($cm)
    {
        $cm->mapManyToOne(array('fieldName' => 'address', 'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address'));

        $this->assertTrue(isset($cm->associationsMappings['address']), "No 'address' in associations map.");
        $this->assertEquals(array(
            'fieldName' => 'address',
            'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address',
            'jsonName' => 'address',
            'sourceDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Person',
            'isOwning' => true,
            'type' => ClassMetadata::MANY_TO_ONE,
        ), $cm->associationsMappings['address']);

        $this->assertArrayHasKey('address', $cm->jsonNames);
        $this->assertEquals('address', $cm->jsonNames['address']);

        return $cm;
    }

    /**
     * @param ClassMetadata $cm
     * @depends testClassName
     */
    public function testMapAttachments($cm)
    {
        $cm->mapAttachments("attachments");

        $this->assertTrue($cm->hasAttachments);
        $this->assertEquals("attachments", $cm->attachmentField);
    }

    /**
     * @depends testClassName
     */
    public function testSerializeUnserialize()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");
        $cm->initializeReflection(new RuntimeReflectionService());
        $cm->indexed = true;

        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $cm->name);

        // property based comparison
        $unserialized = unserialize(serialize($cm));
        $unserialized->wakeupReflection(new RuntimeReflectionService());
        $this->assertEquals($cm, $unserialized);

        $cm->mapField(array('fieldName' => 'id', 'id' => true));
        $cm->mapField(array('fieldName' => 'username', 'type' => 'string', 'indexed' => true));
        $cm->mapField(array('fieldName' => 'created', 'type' => 'datetime'));
        $cm->mapField(array('fieldName' => 'version', 'jsonName' => '_rev', 'isVersionField' => true));
        $cm->mapManyToOne(array('fieldName' => 'address', 'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address'));
        $cm->mapAttachments("attachments");

        // @TODO This is the only way the $reflFields property is initialized
        // as far as I understand the ClassMetadata code. This seems wrong to
        // call here, but otherwise the comparison fails. The $reflFields are
        // accesssed all over the code though – I guess there are some bugs
        // hidden here.
        $cm->wakeupReflection(new RuntimeReflectionService());

        // property based comparison
        $unserialized = unserialize(serialize($cm));
        $unserialized->wakeupReflection(new RuntimeReflectionService());
        $this->assertEquals($cm, $unserialized);
    }

    /**
     * @depends testClassName
     */
    public function testSerializeUnserializeSerializableObject()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\SerializableObject");
        $cm->initializeReflection(new RuntimeReflectionService());

        $cm->mapField(array('fieldName' => 'property', 'type' => 'integer'));

        // @TODO This is the only way the $reflFields property is initialized
        // as far as I understand the ClassMetadata code. This seems wrong to
        // call here, but otherwise the comparison fails. The $reflFields are
        // accesssed all over the code though – I guess there are some bugs
        // hidden here.
        $cm->wakeupReflection(new RuntimeReflectionService());

        // property based comparison
        $unserialized = unserialize(serialize($cm));
        $unserialized->wakeupReflection(new RuntimeReflectionService());
        $this->assertEquals($cm, $unserialized);
    }

    public function testDeriveChildMetadata()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");
        $cm->mapField(array('fieldName' => 'id', 'id' => true));
        $cm->mapField(array('fieldName' =>'username', 'type' => 'string'));
        $cm->mapAttachments('attachments');
        $cm->mapManyToOne(array('targetDocument' => 'Address', 'fieldName' => 'address'));

        $child = new ClassMetadata('Doctrine\Tests\ODM\CouchDB\Mapping\Employee');
        $cm->deriveChildMetadata($child);

        $child->mapField(array('fieldName' => 'status', 'type' => 'string'));

        $this->assertFalse(isset($child->fieldMappings['status']['declared']));

        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Employee", $child->name);

        $this->assertTrue(isset($child->fieldMappings['id']), "id field has to be on child metadata");
        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $child->fieldMappings['id']['declared']);

        $this->assertTrue(isset($child->fieldMappings['username']), "Username field has to be on child metadata");
        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $child->fieldMappings['username']['declared']);

        $this->assertTrue(isset($child->associationsMappings['address']), "address association has to be on child metadata");
        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $child->associationsMappings['address']['declared']);

        $this->assertEquals("attachments", $child->attachmentField);
        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $child->attachmentDeclaredClass);

        return $child;
    }
}

class SerializableObject implements \Serializable
{
    public $property;

    public function serialize()
    {
        return serialize(array('property' => $this->property));
    }

    public function unserialize($data)
    {
        $values = unserialize($data);
        $this->property = $data['property'];
        return $this;
    }
}

class Person
{
    public $id;

    public $username;

    public $created;

    public $address;

    public $version;

    public $attachments;
}

class Address
{
    public $id;
}

class Employee extends Person
{
    public $status;
}
