<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class ManyToManyAssociationTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $userId;
    private $dm;

    public function testSaveManyToMany()
    {
        $this->dm = $this->createDocumentManager();

        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->status = "active";
        $user->name = "Benjamin";

        $group1 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group1->name = "Admin";

        $group2 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group2->name = "User";

        $user->addGroup($group1);
        $user->addGroup($group2);

        $this->dm->persist($user);
        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertType('Doctrine\ODM\CouchDB\PersistentCollection', $user->groups);
        $this->assertFalse($user->groups->isInitialized);
        $this->assertEquals(2, count($user->groups));
        $this->assertTrue($user->groups->isInitialized);

        $group3 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group3->name = "User";
        $user->addGroup($group3);
        $this->dm->persist($group3);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals(3, count($user->groups));
    }
}