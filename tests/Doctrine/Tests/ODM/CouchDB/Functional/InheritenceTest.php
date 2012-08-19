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

    public function testPersistInheritanceReferenceMany()
    {
        $child1 = new CODM25ChildA();
        $child1->foo = "bar";
        $child2 = new CODM25ChildA();
        $child2->foo = "baz";
        $parent = new CODM25Parent();
        $parent->childs[] = $child1;
        $parent->childs[] = $child2;

        $dm = $this->createDocumentManager();
        $dm->persist($parent);
        $dm->persist($parent->childs[0]);
        $dm->persist($parent->childs[1]);
        $dm->flush();

        $dm->clear();

        $parent = $dm->find(__NAMESPACE__ . '\\CODM25Parent', $parent->id);

        $this->assertEquals(2, count($parent->childs));
        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->childs[0]);
        $this->assertEquals('bar', $parent->childs[0]->foo);
        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->childs[1]);
        $this->assertEquals('baz', $parent->childs[1]->foo);
    }

    public function testPersistInheritanceEmbededOne()
    {
        $child = new CODM25ChildA();
        $child->foo = "bar";
        $parent = new CODM25Parent();
        $parent->embed = $child;

        $dm = $this->createDocumentManager();
        $dm->persist($parent);
        $dm->persist($parent->embed);
        $dm->flush();

        $dm->clear();

        $parent = $dm->find(__NAMESPACE__ . '\\CODM25Parent', $parent->id);

        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->embed);
        $this->assertEquals('bar', $parent->embed->foo);
    }

    public function testPersistInheritanceEmbedMany()
    {
        $child1 = new CODM25ChildA();
        $child1->foo = "bar";
        $child2 = new CODM25ChildA();
        $child2->foo = "baz";
        $parent = new CODM25Parent();
        $parent->embeds[] = $child1;
        $parent->embeds[] = $child2;

        $dm = $this->createDocumentManager();
        $dm->persist($parent);
        $dm->persist($parent->embeds[0]);
        $dm->persist($parent->embeds[1]);
        $dm->flush();

        $dm->clear();

        $parent = $dm->find(__NAMESPACE__ . '\\CODM25Parent', $parent->id);

        $this->assertEquals(2, count($parent->embeds));
        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->embeds[0]);
        $this->assertEquals('bar', $parent->embeds[0]->foo);
        $this->assertInstanceOf(__NAMESPACE__ . '\\CODM25ChildA', $parent->embeds[1]);
        $this->assertEquals('baz', $parent->embeds[1]->foo);
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

    /** @ReferenceMany(targetDocument="CODM25Child") */
    public $childs;

    /**
     * @EmbedOne(targetDocument="CODM25Child")
     */
    public $embed;

    /**
     * @EmbedMany(targetDocument="CODM25Child")
     */
    public $embeds;
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
