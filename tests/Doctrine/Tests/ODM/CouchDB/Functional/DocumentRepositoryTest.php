<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class DocumentRepositoryTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    public function testLoadMany()
    {
        $this->dm = $this->createDocumentManager();

        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";

        $user2 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user2->username = "lsmith";
        $user2->status = "active";
        $user2->name = "Lukas";
        
        $this->dm = $this->createDocumentManager();
        $this->dm->persist($user1);
        $this->dm->persist($user2);
        $this->dm->flush();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, $user2->id));
        $this->assertSame($user1, $users[0]);
        $this->assertSame($user2, $users[1]);

        $this->dm->clear();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, $user2->id));
        $this->assertEquals($user1->id, $users[0]->id);
        $this->assertEquals($user2->id, $users[1]->id);
    }

    public function testLoadManyWithMissingIds()
    {
        $this->dm = $this->createDocumentManager();
        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array('missing-id-1', 'missing-id-2'));
        $this->assertEmpty($users);

        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";
        $this->dm = $this->createDocumentManager();
        $this->dm->persist($user1);
        $this->dm->flush();
        $this->dm->clear();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, 'missing-id-2'));
        $this->assertEquals(1, count($users));
    }
}