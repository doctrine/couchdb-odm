<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class RepositoryTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    private $type;

    public function setUp()
    {
        $this->type = 'Doctrine\Tests\Models\CMS\CmsUser';
        $this->dm = $this->createDocumentManager();
    }

    public function testFindMany()
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
        $this->assertEquals(2, count($users));
        $this->assertSame($user1, $users[0]);
        $this->assertSame($user2, $users[1]);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, $user2->id), 1, 0);
        $this->assertEquals(1, count($users));
        $this->assertSame($user1, $users[0]);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, $user2->id), 1, 1);
        $this->assertEquals(1, count($users));
        $this->assertSame($user2, $users[1]);

        $this->dm->clear();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findMany(array($user1->id, $user2->id));
        $this->assertEquals($user1->id, $users[0]->id);
        $this->assertEquals($user2->id, $users[1]->id);
    }

    public function testFindAll()
    {
        for ($i = 0; $i < 10; $i++) {
            $user = new \Doctrine\Tests\Models\CMS\CmsUser();
            $user->username = "beberlei" . $i;
            $user->status = "active";
            $user->name = "Benjamin" . $i;

            $this->dm->persist($user);
        }
        for ($i = 0; $i < 10; $i++) {
            $group = new \Doctrine\Tests\Models\CMS\CmsGroup();
            $group->name = "Group" . $i;
            $this->dm->persist($group);
        }
        $this->dm->flush();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findAll();
        $this->assertEquals(10, count($users));
        $this->assertContainsOnly('Doctrine\Tests\Models\CMS\CmsUser', $users);

        $groups = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsGroup')->findAll();
        $this->assertEquals(0, count($groups), "No results, group is not indexed!");
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

    public function testFindBy()
    {
        for ($i = 0; $i < 10; $i++) {
            $user = new \Doctrine\Tests\Models\CMS\CmsUser();
            $user->username = "beberlei" . $i;
            $user->status = ($i % 2 == 0) ? "active" : "inactive";
            $user->name = "Benjamin";

            $this->dm->persist($user);
        }
        $this->dm->flush();
        
        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active'));

        $this->assertEquals(5, count($users));
        $this->assertContainsOnly('Doctrine\Tests\Models\CMS\CmsUser', $users);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active'), null, 2);
        $this->assertEquals(2, count($users));

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'inactive'));

        $this->assertEquals(5, count($users));
        $this->assertContainsOnly('Doctrine\Tests\Models\CMS\CmsUser', $users);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active', 'username' => 'beberlei0'));
        $this->assertEquals(1, count($users));

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active', 'name' => 'Benjamin'), null, 2);
        $this->assertEquals(2, count($users));
    }

    public function testFindByManyConstraints()
    {
        for ($i = 0; $i < 10; $i++) {
            $user = new \Doctrine\Tests\Models\CMS\CmsUser();
            $user->username = "beberlei" . $i;
            $user->status = ($i % 2 == 0) ? "active" : "inactive";
            $user->name = "Benjamin" . $i;

            $this->dm->persist($user);
        }
        $this->dm->flush();

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('username' => 'beberlei0'));
        $this->assertEquals(1, count($users));
        $this->assertEquals('beberlei0', $users[0]->username);
    }

    public function testFindByIdUsesIdentityMap()
    {
        $this->dm = $this->createDocumentManager();

        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->status = "active";
        $user->name = "Benjamin";

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $user1 = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->find($user->id);
        $user2 = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->find($user->id);

        $this->assertSame($user1, $user2);
    }

    public function testFindByReusesIdentities()
    {
        $this->dm = $this->createDocumentManager();

        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->status = "active";
        $user->name = "Benjamin";

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $user1 = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findOneBy(array('username' => 'beberlei'));
        $user2 = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findOneBy(array('username' => 'beberlei'));

        $this->assertSame($user1, $user2);
    }
}
