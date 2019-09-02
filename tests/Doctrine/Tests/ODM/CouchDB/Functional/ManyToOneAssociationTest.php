<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Tests\Models\CMS\CmsNode;

class ManyToOneAssociationTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $articleId;
    private $userIds = array();
    private $dm;

    public function setUp()
    {
        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";

        $user2 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user2->username = "lsmith";
        $user2->status = "active";
        $user2->name = "Lukas";

        $article = new \Doctrine\Tests\Models\CMS\CmsArticle();
        $article->text = "Foo";
        $article->topic = "Foo";
        $article->setAuthor($user1);

        $this->dm = $this->createDocumentManager();
        $this->dm->persist($user1);
        $this->dm->persist($user2);
        $this->dm->persist($article);
        $this->dm->flush();

        $this->dm->clear();

        $this->articleId = $article->id;
        $this->userIds = array($user1->id, $user2->id);
    }

    public function testSaveWithAssociation()
    {
        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $this->articleId);

        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $article->user);
        $this->assertEquals('beberlei', $article->user->getUsername());
    }

    public function testSwitchAssociation()
    {
        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $this->articleId);
        $otherUser = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userIds[1]);

        $article->setAuthor($otherUser);

        $this->dm->flush();
        $this->dm->clear();

        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $this->articleId);
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $article->user);
        $this->assertEquals('lsmith', $article->user->getUsername());
    }

    public function testReuseIdentityMap()
    {
        $author = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userIds[0]);
        $article = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $this->articleId);

        $this->assertNotInstanceOf( 'Doctrine\ODM\CouchDB\Proxy\ProxyFactory', $article->user);
        $this->assertSame($author, $article->user);
    }

    public function testNullReference()
    {
        $this->markTestIncomplete('Test that persisting and hydrating null works smoothly.');
    }

    public function testNoTargetDocument()
    {
        $node = new CmsNode();
        $node->content = $this->dm->find('Doctrine\Tests\Models\CMS\CmsArticle', $this->articleId);

        $this->dm->persist($node);
        $this->dm->flush();
        $this->dm->clear();

        $node = $this->dm->find('Doctrine\Tests\Models\CMS\CmsNode', $node->id);
        $this->assertInstanceof('Doctrine\Tests\Models\CMS\CmsArticle', $node->content);
    }
}
