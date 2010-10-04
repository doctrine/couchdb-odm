<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_Testcase
{
    public function setUp()
    {
        $this->dm = \Doctrine\ODM\CouchDB\DocumentManager::create();
    }

    public function testNotMappedThrowsException()
    {
        $cmf = new ClassMetadataFactory($this->dm);

        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');
        $cmf->getMetadataFor('unknown');
    }

    public function testGetMapping()
    {
        $cmf = new ClassMetadataFactory($this->dm);

        $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass');

        $cmf->setMetadataFor('stdClass', $cm);

        $this->assertTrue($cmf->hasMetadataFor('stdClass'));
        $this->assertSame($cm, $cmf->getMetadataFor('stdClass'));
    }

    public function testGetAllMetadata()
    {
        $driver = new \Doctrine\ODM\CouchDB\Mapping\Driver\PHPDriver(array(__DIR__));
        $this->dm->getConfiguration()->setMetadataDriverImpl($driver);

        $cmf = new ClassMetadataFactory($this->dm);

        $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass');
        $cmf->setMetadataFor('stdClass', $cm);

        $metadata = $cmf->getAllMetadata();

        $this->assertType('array', $metadata);
    }
}