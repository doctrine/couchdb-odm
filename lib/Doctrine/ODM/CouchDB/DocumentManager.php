<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;

class DocumentManager
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    private $unitOfWork = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->metadataFactory = new ClassMetadataFactory();
        $this->unitOfWork = new UnitOfWork($this);
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getClassMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @param  string $class
     * @return ClassMetadata
     */
    public function getClassMetadata($class)
    {
        return $this->metadataFactory->getMetadataFor($class);
    }

    public function find($class, $id)
    {
        return $this->unitOfWork->getDocumentPersister($class)->load($id);
    }

    public function persist($object)
    {
        $this->unitOfWork->scheduleInsert($object);
    }

    public function remove($object)
    {
        $this->unitOfWork->scheduleRemove($object);
    }

    public function flush()
    {
        
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }
}
