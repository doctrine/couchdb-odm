<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

abstract class AbstractConverterTest extends \PHPUnit_Framework_TestCase
{
    protected $uow;
    
    protected function createConverter($className)
    {
        $instance = new $className;
        return new \Doctrine\ODM\CouchDB\Mapping\Converter($instance, $className, $this->uow);

    }

    protected function loadDriverForCMSDocuments()
    {
        $annotationDriver = $this->loadDriver();
        $annotationDriver->addPaths(array(__DIR__ . '/../../../../../Doctrine/Tests/Models/CMS'));
        return $annotationDriver;
    }

    protected function loadDriver()
    {
        $cache = new \Doctrine\Common\Cache\ArrayCache();
        $reader = new \Doctrine\Common\Annotations\AnnotationReader($cache);
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
        return new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader);
    }


}

class UnitOfWorkMock
{
    private $dm;
    public function __construct($dm) 
    {
        $this->dm = $dm;
    }
    public function getDocumentManager()
    {
        return $this->dm;
    }
    
    public function getDocumentIdentifier($doc)
    {
        return ''.\spl_object_hash($doc);
    }

    public function getMetadataResolver()
    {
        return $this->dm->getConfiguration()->getMetadataResolverImpl();
    }

    public function getDocumentState($doc)
    {
        return \Doctrine\ODM\CouchDB\UnitOfWork::STATE_MANAGED;
    }
}

