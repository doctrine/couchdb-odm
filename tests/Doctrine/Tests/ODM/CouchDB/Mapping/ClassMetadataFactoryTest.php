<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_Testcase
{
    public function testNotMappedThrowsException()
    {
        $cmf = new ClassMetadataFactory();

        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');
        $cmf->getMetadataFor('unknown');
    }

    public function testGetMapping()
    {
        $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass');
        $cmf = new ClassMetadataFactory();
        $cmf->setMetadataFor($cm);

        $this->assertSame($cm, $cmf->getMetadataFor('stdClass'));

    }
}