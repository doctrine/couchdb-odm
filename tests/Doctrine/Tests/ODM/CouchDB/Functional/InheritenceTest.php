<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class InheritenceTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    public function testPersistInheritanceReferenceOne()
    {
        $child = new CODM25ChildA();
        $child->foo = "bar";
        $parent = new CODM25Parent();
        $parent->child = $child;

        $dm = $this->createDocumentManager();
        $dm->persist($parent);
        $dm->persist($parent->child);
        $dm->flush();

        $dm->clear();

        $parent = $dm->find(__NAMESPACE__ . '\\CODM25Parent', $parent->id);

        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->child);
        $this->assertEquals('bar', $parent->child->foo);
    }
}

/**
 * @Document
 */
class CODM25Parent
{
    /** @Id */
    public $id;

    /** @ReferenceOne(targetDocument="CODM25Child") */
    public $child;
}

/**
 * @Document
 * @InheritanceRoot
 */
abstract class CODM25Child
{
    /** @Id */
    public $id;
}

/**
 * @Document
 */
class CODM25ChildA extends CODM25Child
{
    /** @Field */
    public $foo;
}
