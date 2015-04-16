<?php

namespace Doctrine\Tests\ODM\CouchDB\View;

class FolderDesignDocumentTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    public function testGetViewsBasedOnFileStructure()
    {
        $designDoc = new \Doctrine\CouchDB\View\FolderDesignDocument(__DIR__ . "/../../../Models/CMS/_files/");

        $this->assertEquals(
            array(
                "rewrites" => array(
                    array("from"=>"/from/url","to"=>"/to/url",
                          "query"=>array("descending"=>"true","include_docs"=>"true")
                        ),
                    array("from"=>"/from/url2","to"=>"/to/url2",
                          "query"=>array("descending"=>"true","include_docs"=>"true")
                        ),
                    ),
                "views" => array("username" => array("map" => "function(doc) {
    if (doc.type == 'Doctrine.Tests.Models.CMS.CmsUser') {
        emit(doc.username, doc._id);
    }
}")),
                "language" => "javascript",
        ), $designDoc->getData());
    }
}
