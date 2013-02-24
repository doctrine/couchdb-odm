<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class MappedSuperclassTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{

    /**
     *
     * @var \Doctrine\ODM\CouchDB\DocumentManager
     */
    protected $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testCRUD()
    {
        $e = new DocumentSubClass;
        $e->setName('Roman');
        $e->setMapped1(42);
        $e->setMapped2('bar');

        $related = new MappedSuperclassRelated1();
        $related->setName('Related');
        $e->setMappedRelated1($related);

        $this->dm->persist($e);
        $this->dm->flush();
        $this->dm->clear();

        $id = $e->getId();

        $e2 = $this->dm->find(__NAMESPACE__.'\DocumentSubClass', $id);

        $this->assertNotNull($e2);
        $this->assertEquals('Roman', $e2->getName());
        $this->assertNotNull($e2->getMappedRelated1());
        $this->assertInstanceOf(__NAMESPACE__.'\MappedSuperclassRelated1', $e2->getMappedRelated1());
        $this->assertEquals(42, $e2->getMapped1());
        $this->assertEquals('bar', $e2->getMapped2());
    }
}

/** @MappedSuperclass */
class MappedSuperclassBase
{
    /** @Field(type="string") */
    private $mapped1;

    /** @Field(type="string") */
    private $mapped2;

    /**
     * @EmbedOne(targetDocument="MappedSuperclassRelated1")
     */
    private $mappedRelated1;

    public function setMapped1($val)
    {
        $this->mapped1 = $val;
    }

    public function getMapped1()
    {
        return $this->mapped1;
    }

    public function setMapped2($val)
    {
        $this->mapped2 = $val;
    }

    public function getMapped2()
    {
        return $this->mapped2;
    }

    public function setMappedRelated1($mappedRelated1)
    {
        $this->mappedRelated1 = $mappedRelated1;
    }

    public function getMappedRelated1()
    {
        return $this->mappedRelated1;
    }
}

/** @EmbeddedDocument */
class MappedSuperclassRelated1
{

    /** @Field(type="string") */
    private $name;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getId()
    {
        return $this->id;
    }
}

/** @Document */
class DocumentSubClass extends MappedSuperclassBase
{
    /** @Id */
    private $id;

    /** @Field(type="string") */
    private $name;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getId()
    {
        return $this->id;
    }
}