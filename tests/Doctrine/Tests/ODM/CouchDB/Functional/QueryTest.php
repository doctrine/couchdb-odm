<?php

namespace Doctrine\ODM\CouchDB\Functional;

class QueryTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function testQuery()
    {
        $designDocPath = __DIR__ . "/../../../Models/CMS/_files";
        $this->dm = $this->createDocumentManager();
        $this->dm->getConfiguration()
                 ->addDesignDocument('cms', 'Doctrine\CouchDB\View\FolderDesignDocument', $designDocPath);

        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";

        $user2 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user2->username = "lsmith";
        $user2->status = "active";
        $user2->name = "Lukas";

        $this->dm->persist($user1);
        $this->dm->persist($user2);
        $this->dm->flush();
        $this->dm->clear();

        $result = $this->dm->createQuery('cms', 'username')
                           ->onlyDocs(true)
                           ->setKey('lsmith')
                           ->execute();

        $this->assertEquals(1, count($result));
        $this->assertEquals('lsmith', $result[0]->username);

        $result = $this->dm->createQuery('cms', 'username')
                           ->onlyDocs(true)
                           ->setKey('beberlei')
                           ->execute();

        $this->assertEquals(1, count($result));
        $this->assertEquals('beberlei', $result[0]->username);
    }
}