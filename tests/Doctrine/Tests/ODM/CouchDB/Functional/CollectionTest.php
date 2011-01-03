<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class CollectionTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testReplaceArrayWithPersistentCollections()
    {

        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";

        $group1 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group1->name = "Admin";

        $user1->addGroup($group1);

        $this->dm->persist($user1);
        $this->dm->persist($group1);
        $this->dm->flush();

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentIdsCollection', $user1->groups);
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentViewCollection', $group1->users);
    }
}