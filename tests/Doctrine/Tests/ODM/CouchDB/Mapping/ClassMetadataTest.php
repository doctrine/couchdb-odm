<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class ClassMetadataTest extends \PHPUnit_Framework_Testcase
{
    public function testClassName()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\User");

        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\User", $cm->name);
        $this->assertType('ReflectionClass', $cm->reflClass);

        return $cm;
    }

    /**
     * @depends testClassName
     */
    public function testMapId($cm)
    {
        $cm->mapId(array('name' => 'id'));

        $this->assertTrue(isset($cm->properties['id']));
        $this->assertEquals(array('name' => 'id', 'resultkey' => '_id', 'type' => 'string'), $cm->properties['id']);

        $this->assertEquals('id', $cm->identifier);

        return $cm;
    }

    /**
     * @depends testMapId
     */
    public function testMapProperty($cm)
    {
        $cm->mapProperty(array('name' => 'username', 'type' => 'string'));
        $cm->mapProperty(array('name' => 'created', 'type' => 'datetime'));

        $this->assertTrue(isset($cm->properties['username']));
        $this->assertTrue(isset($cm->properties['created']));

        $this->assertEquals(array('name' => 'username', 'type' => 'string', 'resultkey' => 'username'), $cm->properties['username']);
        $this->assertEquals(array('name' => 'created', 'type' => 'datetime', 'resultkey' => 'created'), $cm->properties['created']);

        return $cm;
    }

    /**
     * @depends testMapProperty
     */
    public function testMapPropertyWithoutNameThrowsException($cm)
    {
        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');

        $cm->mapProperty(array());

        return $cm;
    }

    /**
     * @depends testMapProperty
     */
    public function testMapUnknownPropertyThrowsReflectionException($cm)
    {
        $this->setExpectedException('ReflectionException');

        $cm->mapProperty(array('name' => 'foobar'));
        
        return $cm;
    }

    /**
     * @depends testMapProperty
     */
    public function testReflectionProperties($cm)
    {
        $this->assertType('ReflectionProperty', $cm->reflProps['username']);
        $this->assertType('ReflectionProperty', $cm->reflProps['created']);
    }
    
    /**
     * @depends testMapProperty
     */
    public function testNewInstance($cm)
    {
        $instance1 = $cm->newInstance();
        $instance2 = $cm->newInstance();

        $this->assertType('Doctrine\Tests\ODM\CouchDB\Mapping\User', $instance1);
        $this->assertNotSame($instance1, $instance2);
    }

    public function testMapPropertyWithoutType_DefaultsToString()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\User");

        $cm->mapProperty(array('name' => 'username'));

        $this->assertEquals(array('name' => 'username', 'type' => 'string', 'resultkey' => 'username'), $cm->properties['username']);
    }

    /**
     * @param ClassMetadata $cm
     * @depends testClassName
     */
    public function testMapAssociationManyToOne($cm)
    {
        $cm->mapManyToOne(array('name' => 'address', 'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address'));

        $this->assertTrue(isset($cm->associations['address']), "No 'address' in associations map.");
        $this->assertEquals(array(
            'name' => 'address',
            'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address',
            'sourceDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\User',
            'isOwning' => true,
            'type' => ClassMetadata::MANY_TO_ONE,
        ), $cm->associations['address']);

        return $cm;
    }
}

class User
{
    public $id;

    public $username;

    public $created;

    public $address;
}

class Address
{
    public $id;
}