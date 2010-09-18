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
        $cm->mapProperty(array('name' => 'id', 'id' => true));

        $this->assertTrue(isset($cm->properties['id']));
        $this->assertEquals(array('name' => 'id', 'id' => true, 'type' => 'string', 'resultkey' => 'id'), $cm->properties['id']);

        $this->assertEquals(array('id'), $cm->identifier);

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
}

class User
{
    public $id;

    public $username;

    public $created;
}