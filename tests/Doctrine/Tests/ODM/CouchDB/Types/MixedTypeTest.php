<?php

namespace Doctrine\Tests\ODM\CouchDB\Types;

use Doctrine\ODM\CouchDB\Types\Type;

class MixedTypeTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $type;

    public function setUp()
    {
        $this->type = Type::getType('mixed');
    }

    public function testRegisteredInstance()
    {
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Types\MixedType', $this->type);
    }

    static public function dataConvertRoundtrip()
    {
        return array(
            array('string', 'string'),
            array(1234, 1234),
            array(1.345, 1.345),
            array(null, null),
            array(true, true),
            array(false, false),
            array(array('foo'), array('foo')),
        );
    }

    /**
     * @dataProvider dataConvertRoundtrip
     * @param string $expected
     * @param string $value
     */
    public function testConvertRoundtrip($expected, $value)
    {
        $this->assertEquals($expected, $this->type->convertToCouchDBValue($this->type->convertToPHPValue($value)));
    }
}