<?php

namespace Doctrine\Tests\ODM\CouchDB\Types;

use Doctrine\ODM\CouchDB\Types\Type;

class DateTimeTypeTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $type;

    public function setUp()
    {
        $this->type = Type::getType('datetime');
    }

    public function testRegisteredInstance()
    {
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Types\DateTimeType', $this->type);
    }

    static public function dataConvertRoundtrip()
    {
        return array(
            array('2010-10-20 14:23:37.123456', '2010-10-20 14:23:37.123456'),
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