<?php

namespace Doctrine\Tests\ODM\CouchDB\View;

class FolderDesignDocumentTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    public function testGetViewsBasedOnFileStructure()
    {
        $designDoc = new \Doctrine\ODM\CouchDB\View\FolderDesignDocument(__DIR__ . "/../../../Models/CMS/_files/");

        $this->assertEquals(array(
            "views" => array("username" => array("map" => "function(doc) {
    if (doc.doctrine_metadata.type == 'Doctrine.Tests.Models.CMS.CmsUser') {
        emit(doc.username, doc._id);
    }
}"))
        ), $designDoc->getData());
    }
}