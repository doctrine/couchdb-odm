<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class ManyToOneAssociationTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    public function setUp()
    {
        $this->useModelSet('cms');
        $this->type = 'Doctrine\Tests\Models\CMS\CmsUser';
    }

    public function testSaveWithAssociation()
    {
        $user = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user->username = "beberlei";
        $user->status = "active";
        $user->name = "Benjamin";

        $article = new \Doctrine\Tests\Models\CMS\CmsArticle();
        $article->text = "Foo";
        $article->topic = "Foo";
        $article->setAuthor($user);

        $dm = $this->createDocumentManager();
        $dm->persist($user);
        $dm->persist($article);
        $dm->flush();

        $dm->clear();

        $article = $dm->find($this->type, $article->id);
        $this->assertType($this->type, $article->user);
        $this->assertEquals('beberlei', $article->user->username);
    }
}
