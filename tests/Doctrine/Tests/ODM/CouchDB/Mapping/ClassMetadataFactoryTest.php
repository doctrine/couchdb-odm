<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));
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

    public function testGetMetadataForDocumentWithMappedSuperclass()
    {
        $class = $this->dm->getMetadataFactory()->getMetadataFor(__NAMESPACE__ ."\\Child");
        $this->assertFalse($class->isMappedSuperclass, "Child is not a mapped superclass!");
        $this->assertEquals(__NAMESPACE__ ."\\Child", $class->rootDocumentName);

        $class = $this->dm->getMetadataFactory()->getMetadataFor(__NAMESPACE__ ."\\ChildChild");
        $this->assertFalse($class->isMappedSuperclass, "ChildChild is not a mapped superclass!");
        $this->assertEquals(__NAMESPACE__ ."\\Child", $class->rootDocumentName);

        $class = $this->dm->getMetadataFactory()->getMetadataFor(__NAMESPACE__ ."\\Super");
        $this->assertTrue($class->isMappedSuperclass);
    }
}

/**
 * @MappedSuperclass
 */
class Super
{
    /** @Id */
    private $id;
}

/**
 * @Document
 */
class Child extends Super
{
    /**
     * @Field(type="string")
     */
    private $var;
}

/**
 * @Document
 */
class ChildChild extends Child
{
    /**
     * @Field(type="string")
     */
    private $var2;
}
