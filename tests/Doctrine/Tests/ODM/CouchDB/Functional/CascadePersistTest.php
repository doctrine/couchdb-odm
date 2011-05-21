<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class CascadePersistTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();

        $class = $this->dm->getClassMetadata('Doctrine\Tests\Models\CMS\CmsUser');
        $class->associationsMappings['groups']['cascade'] = ClassMetadata::CASCADE_PERSIST;

        $class = $this->dm->getClassMetadata('Doctrine\Tests\Models\CMS\CmsGroup');
        $class->associationsMappings['users']['cascade'] = ClassMetadata::CASCADE_PERSIST;

        $class = $this->dm->getClassMetadata('Doctrine\Tests\Models\CMS\CmsArticle');
        $class->associationsMappings['user']['cascade'] = ClassMetadata::CASCADE_PERSIST;
    }

    public function testCascadePersistCollection()
    {
        $group1 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group1->name = "Test!";

        $group2 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group2->name = "Test!";

        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        $user->addGroup($group1);
        $user->addGroup($group2);

        $this->dm->persist($user);

        $this->assertTrue($this->dm->contains($group1));
        $this->assertTrue($this->dm->contains($group2));

        $this->dm->flush();
        $this->dm->clear();

        $pUser = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals(2, count($pUser->groups));
    }

    public function testCascadePersistForManagedEntity()
    {
        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        $this->dm->persist($user);
        $this->dm->flush();
        
        $group1 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group1->name = "Test!";

        $group2 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group2->name = "Test!";

        $user->addGroup($group1);
        $user->addGroup($group2);
        $this->dm->persist($user);

        $this->assertTrue($this->dm->contains($group1));
        $this->assertTrue($this->dm->contains($group2));

        $this->dm->flush();
        $this->dm->clear();

        $pUser = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals(2, count($pUser->groups));
    }

    public function testCascadePersistSingleDocument()
    {
        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";

        $article = new \Doctrine\Tests\Models\CMS\CmsArticle();
        $article->text = "foo";
        $article->topic = "bar";
        $article->user = $user;

        $this->dm->persist($article);

        $this->assertTrue($this->dm->contains($user));

        $this->dm->flush();
        $this->dm->clear();

        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $article->id);
        $this->assertEquals($user->id, $article->user->getId());
    }

    public function testCascadeManagedDocumentCollectionDuringFlush()
    {
        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $group1 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group1->name = "Test!";

        $group2 = new \Doctrine\Tests\Models\CMS\CmsGroup();
        $group2->name = "Test!";

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $user->addGroup($group1);
        $user->addGroup($group2);

        $this->assertFalse($this->dm->contains($group1));
        $this->assertFalse($this->dm->contains($group2));

        $this->dm->flush();
        $this->dm->clear();

        $pUser = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals(2, count($pUser->groups));
    }

    public function testCascadeManagedDocumentReferenceDuringFlush()
    {
        $article = new \Doctrine\Tests\Models\CMS\CmsArticle();
        $article->text = "foo";
        $article->topic = "bar";

        $this->dm->persist($article);

        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        $article->user = $user;

        $this->assertFalse($this->dm->contains($user));

        $this->dm->flush();
        $this->dm->clear();

        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $article->id);
        $this->assertEquals($user->id, $article->user->getId());
    }
}