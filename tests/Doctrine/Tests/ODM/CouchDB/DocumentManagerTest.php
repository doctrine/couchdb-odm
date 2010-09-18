<?php

namespace Doctrine\Tests\ODM\CouchDB;

class DocumentManagerTest extends CouchDBTestCase
{
    public function testNewInstanceFromConfiguration()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $this->assertType('Doctrine\ODM\CouchDB\DocumentManager', $dm);
        $this->assertSame($config, $dm->getConfiguration());
    }

    public function testGetClassMetadataFactory()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $this->assertType('Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory', $dm->getClassMetadataFactory());
    }

    public function testGetClassMetadataFor()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();

        $cmf = $dm->getClassMetadataFactory();
        $cmf->setMetadataFor(new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass'));

        $this->assertType('Doctrine\ODM\CouchDB\Mapping\ClassMetadata', $dm->getClassMetadata('stdClass'));
    }

    /**
     * @return \Doctrine\ODM\CouchDB\DocumentManager
     */
    private function createTestDocumentManager()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $dm = $config->newDocumentManager();
        return $dm;
    }
}