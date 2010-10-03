<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
use Doctrine\ODM\CouchDB\HTTP\Client;

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

    private $proxyFactory = null;

    /**
     * @var array
     */
    private $repositories = array();

    /**
     * @var CouchDBClient
     */
    private $couchDBClient = null;

    public function __construct(Configuration $config = null)
    {
        $this->config = $config ? $config : new Configuration();
        $this->metadataFactory = new ClassMetadataFactory($this);
        $this->unitOfWork = new UnitOfWork($this);
        $this->proxyFactory = new Proxy\ProxyFactory($this, $this->config->getProxyDir(), $this->config->getProxyNamespace(), true);
    }

    /**
     * @return CouchDBClient
     */
    public function getCouchDBClient()
    {
        if ($this->couchDBClient === null) {
            $this->couchDBClient = new CouchDBClient($this->getConfiguration()->getHttpClient(), $this->getConfiguration()->getDatabase());
        }
        return $this->couchDBClient;
    }

    /**
     * Creates a new Document that operates on the given Mongo connection
     * and uses the given Configuration.
     *
     * @param Doctrine\ODM\CouchDB\HTTP\Client
     * @param Doctrine\ODM\CouchDB\Configuration $config
     */
    public static function create(Configuration $config = null)
    {
        return new DocumentManager($config);
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
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

    /**
     * Find the Document with the given id.
     *
     * Will return null if the document wasn't found.
     *
     * @param string $documentName
     * @param string $id
     * @return object
     */
    public function find($documentName, $id)
    {
        return $this->getDocumentRepository($documentName)->find($id);
    }

    /**
     * @param  string $documentName
     * @return Doctrine\ODM\CouchDB\DocumentRepository
     */
    public function getDocumentRepository($documentName)
    {
        $documentName  = ltrim($documentName, '\\');
        if (!isset($this->repositories[$documentName])) {
            $class = $this->getClassMetadata($documentName);
            if ($class->customRepositoryClassName) {
                $repositoryClass = $class->customRepositoryClassName;
            } else {
                $repositoryClass = 'Doctrine\ODM\CouchDB\DocumentRepository';
            }
            $this->repositories[$documentName] = new $repositoryClass($this, $class);
        }
        return $this->repositories[$documentName];
    }

    /**
     * Create a Query for the view in the specified design document.
     * 
     * @param  string $designDocName
     * @param  string $viewName
     * @return Doctrine\ODM\CouchDB\View\Query
     */
    public function createQuery($designDocName, $viewName)
    {
        $designDoc = $this->config->getDesignDocumentClass($designDocName);
        if ($designDoc) {
            $designDoc = new $designDoc;
        }
        $query = new View\Query($this->config->getHttpClient(), $this->config->getDatabase(), $designDocName, $viewName, $designDoc);
        $query->setDocumentManager($this);
        return $query;
    }

    /**
     * Create a Native query for the view of the specified design document.
     *
     * A native query will return an array of data from the &include_docs=true parameter.
     *
     * @param  string $designDocName
     * @param  string $viewName
     * @return View\NativeQuery
     */
    public function createNativeQuery($designDocName, $viewName)
    {
        $designDoc = $this->config->getDesignDocumentClass($designDocName);
        if ($designDoc) {
            $designDoc = new $designDoc;
        }
        return new View\NativeQuery($this->config->getHttpClient(), $this->config->getDatabase(), $designDocName, $viewName, $designDoc);
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
     * Refresh the given document by querying the CouchDB to get the current state.
     *
     * @param object $document
     */
    public function refresh($document)
    {
        $this->getDocumentRepository(get_class($document))->refresh($document);
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
