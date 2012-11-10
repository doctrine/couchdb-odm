<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\CouchDB\View\DesignDocument;
use Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase;

class LuceneQueryTest extends CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testLuceneIndexing()
    {
        $this->dm->getConfiguration()->addDesignDocument(
            'lucene_users', 'Doctrine\Tests\ODM\CouchDB\Functional\LuceneQueryDesignDoc', array()
        );

        $query    = $this->dm->createLuceneQuery('lucene_users', 'by_name');
        $response = $query->createDesignDocument();

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

        $query->setQuery("Lukas");

        try {
            $result = $query->execute();
        } catch(\Doctrine\CouchDB\HTTP\HTTPException $e) {
            if ($e->getCode() == 404) {
                $this->markTestSkipped("Lucene is not integrated");
            } else {
                throw $e;
            }
        }

        $this->assertEquals(1, count($result));
        foreach ($result AS $user) {
            $this->assertEquals($user2->id, $user['id']);
            $this->assertEquals(1, $user['score']);
        }

        $query->setIncludeDocs(true)->setDocumentName('Doctrine\Tests\Models\CMS\CmsUser');
        $result = $query->execute();
        $this->assertSame($user2, $result[0]['doc']);
    }
}

class LuceneQueryDesignDoc implements DesignDocument
{
    public function getData()
    {
        return array(
            "fulltext" => array(
                "by_name" => array(
                    "index" => "function(doc) {
                        var ret = new Document();
                        ret.add(doc.name);
                        ret.add(doc.type, {field: \"type\"});
                        return ret;
                    }"
                )
            ),
        );
    }
}
