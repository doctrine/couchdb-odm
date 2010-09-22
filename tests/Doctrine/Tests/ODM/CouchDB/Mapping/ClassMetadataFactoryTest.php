<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_Testcase
{
    public function testNotMappedThrowsException()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create();
        $cmf = new ClassMetadataFactory($dm);

        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');
        $cmf->getMetadataFor('unknown');
    }

    public function testGetMapping()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create();
        $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass');

        $cmf = new ClassMetadataFactory($dm);
        $cmf->setMetadataFor('stdClass', $cm);

        $this->assertSame($cm, $cmf->getMetadataFor('stdClass'));

    }
}