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

    /**
     * @var UnitOfWork
     */
    private $unitOfWork = null;

    /**
     * @var CouchDBClient
     */
    private $couchClient = null;

    private $proxyFactory = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->metadataFactory = new ClassMetadataFactory();
        $this->unitOfWork = new UnitOfWork($this);
        // TODO: Add configuration!
        $this->proxyFactory = new Proxy\ProxyFactory($this, $this->config->getProxyDir(), 'MyProxies', true);
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @return CouchDBClient
     */
    public function getCouchDBClient()
    {
        if ($this->couchClient === null) {
            $this->couchClient = new CouchDBClient($this->config);
        }
        return $this->couchClient;
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

    public function findById($id)
    {
        return $this->unitOfWork->getDocumentPersister()->load($id);
    }

    public function find()
    {
        // TODO implement
    }

    public function persist($object)
    {
        $this->unitOfWork->scheduleInsert($object);
    }

    public function remove($object)
    {
        $this->unitOfWork->scheduleRemove($object);
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $documentName The name of the entity type.
     * @param mixed $identifier The entity identifier.
     * @return object The entity reference.
     */
    public function getReference($documentName, $identifier)
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($documentName, '\\'));

        // Check identity map first, if its already in there just return it.
        if ($document = $this->unitOfWork->tryGetById($identifier)) {
            return $document;
        }
        $document = $this->proxyFactory->getProxy($class->name, $identifier);
        $this->unitOfWork->registerManaged($document, $identifier, null);

        return $document;
    }

    public function flush()
    {
        $this->unitOfWork->flush(); // todo: rename commit
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    public function clear()
    {
        // Todo: Do a real delegated clear?
        $this->unitOfWork = new UnitOfWork($this);
    }
}
