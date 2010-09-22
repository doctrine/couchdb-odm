<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class AnnotationDriverTest extends AbstractMappingDriverTest
{
    /**
     * @group DDC-268
     */
    public function testLoadMetadataForNonDocumentThrowsException()
    {
        $cm = new ClassMetadata('stdClass');
        $reader = new \Doctrine\Common\Annotations\AnnotationReader(new \Doctrine\Common\Cache\ArrayCache());
        $annotationDriver = new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader);

        $this->setExpectedException('Doctrine\ODM\CouchDB\CouchDBException');
        $annotationDriver->loadMetadataForClass('stdClass', $cm);
    }

    /**
     * @group DDC-268
     */
    public function testColumnWithMissingTypeDefaultsToString()
    {
        $cm = new ClassMetadata('Doctrine\Tests\ODM\CouchDB\Mapping\ColumnWithoutType');
        $reader = new \Doctrine\Common\Annotations\AnnotationReader(new \Doctrine\Common\Cache\ArrayCache());
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
        $annotationDriver = new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader);

        $annotationDriver->loadMetadataForClass('Doctrine\ODM\CouchDB\Tests\Mapping\InvalidColumn', $cm);
        $this->assertEquals('id', $cm->fieldMappings['id']['type']);
    }

    /**
     * @group DDC-318
     */
    public function testGetAllClassNamesIsIdempotent()
    {
        $annotationDriver = $this->loadDriverForCMSDocuments();
        $original = $annotationDriver->getAllClassNames();

        $annotationDriver = $this->loadDriverForCMSDocuments();
        $afterTestReset = $annotationDriver->getAllClassNames();

        $this->assertEquals($original, $afterTestReset);
    }

    /**
     * @group DDC-318
     */
    public function testGetAllClassNamesReturnsAlreadyLoadedClassesIfAppropriate()
    {
        $rightClassName = 'Documents\CmsUser';
        $this->ensureIsLoaded($rightClassName);

        $annotationDriver = $this->loadDriverForCMSDocuments();
        $classes = $annotationDriver->getAllClassNames();

        $this->assertContains($rightClassName, $classes);
    }

    /**
     * @group DDC-318
     */
    public function testGetClassNamesReturnsOnlyTheAppropriateClasses()
    {
        $extraneousClassName = __NAMESPACE__.'\ColumnWithoutType';
        $this->ensureIsLoaded($extraneousClassName);

        $annotationDriver = $this->loadDriverForCMSDocuments();
        $classes = $annotationDriver->getAllClassNames();

        $this->assertNotContains($extraneousClassName, $classes);
    }

    protected function loadDriverForCMSDocuments()
    {
        $annotationDriver = $this->loadDriver();
        $annotationDriver->addPaths(array(__DIR__ . '/../../../../../Documents'));
        return $annotationDriver;
    }

    protected function loadDriver()
    {
        $cache = new \Doctrine\Common\Cache\ArrayCache();
        $reader = new \Doctrine\Common\Annotations\AnnotationReader($cache);
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
        return new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader);
    }

    protected function ensureIsLoaded($entityClassName)
    {
        new $entityClassName;
    }
}

/**
 * @Document
 */
class ColumnWithoutType
{
    /** @Id */
    public $id;
}