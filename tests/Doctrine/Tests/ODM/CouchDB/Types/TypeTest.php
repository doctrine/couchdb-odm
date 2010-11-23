<?php

namespace Doctrine\Tests\ODM\CouchDB\Types;

use Doctrine\ODM\CouchDB\Types\Type;

class TypeTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    public function testOverwriteNonExistantType()
    {
        $this->setExpectedException("Doctrine\ODM\CouchDB\Types\TypeException");

        Type::overrideType('foobar', 'Doctrine\ODM\CouchDB\Types\MixedType');
    }

    public function testAddExistantType()
    {
        $this->setExpectedException("Doctrine\ODM\CouchDB\Types\TypeException");

        Type::addType('mixed', 'Doctrine\ODM\CouchDB\Types\MixedType');
    }

    public function testHasType()
    {
        $this->assertTrue(Type::hasType('mixed'));
    }

    public function testGetTypesMap()
    {
        $this->assertArrayHasKey('mixed', Type::getTypesMap());
    }
}