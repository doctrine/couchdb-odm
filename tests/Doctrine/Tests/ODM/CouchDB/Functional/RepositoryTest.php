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

    public function testFindBy()
    {
        for ($i = 0; $i < 10; $i++) {
            $user = new \Doctrine\Tests\Models\CMS\CmsUser();
            $user->username = "beberlei" . $i;
            $user->status = ($i % 2 == 0) ? "active" : "inactive";
            $user->name = "Benjamin" . $i;

            $this->dm->persist($user);
        }
        $this->dm->flush();
        
        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active'));

        $this->assertEquals(5, count($users));
        $this->assertContainsOnly('Doctrine\Tests\Models\CMS\CmsUser', $users);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'inactive'));

        $this->assertEquals(5, count($users));
        $this->assertContainsOnly('Doctrine\Tests\Models\CMS\CmsUser', $users);

        $users = $this->dm->getRepository('Doctrine\Tests\Models\CMS\CmsUser')->findBy(array('status' => 'active', 'username' => 'beberlei0'));
        $this->assertEquals(1, count($users));
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
}