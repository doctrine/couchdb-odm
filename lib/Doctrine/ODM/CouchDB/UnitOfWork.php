<?php

namespace Doctrine\ODM\CouchDB;

class UnitOfWork
{
    /**
     * @var DocumentManager
     */
    private $dm = null;

    private $identityMap = array();

    /**
     * The entity persister instances used to persist entity instances.
     *
     * @var array
     */
    private $persisters = array();

    /**
     * The collection persister instances used to persist collections.
     *
     * @var array
     */
    private $collectionPersisters = array();

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function createDocument($class, $data)
    {
        $metadata = $this->dm->getClassMetadata($class);

        $idHash = array();
        foreach ($metadata->identifier AS $idProperty) {
            $idHash[] = $data[$idProperty];
        }
        $idHash = implode(" ", $idHash);

        $overrideLocalValues = true;
        if (isset($this->identityMap[$metadata->name][$idHash])) {
            $doc = $this->identityMap[$metadata->name][$idHash];
            $overrideLocalValues = false;

            if ($doc instanceof Proxy && $doc->__isInitialized__) {
                $overrideLocalValues = true;
            }
        } else {
            $doc = $metadata->newInstance();
            $this->identityMap[$metadata->name][$idHash] = $doc;
        }

        if ($overrideLocalValues) {
            foreach ($metadata->reflProps AS $prop => $reflProp) {
                /* @var $reflProp ReflectionProperty */
                $reflProp->setValue($doc, $data[$prop]);
            }
        }

        return $doc;
    }

    /**
     * Gets the DocumentPersister for an Entity.
     *
     * @param string $documentName  The name of the Document.
     * @return Doctrine\ODM\CouchDB\Persisters\BasicDocumentPersister
     */
    public function getDocumentPersister($documentName)
    {
        if ( ! isset($this->persisters[$documentName])) {
            $class = $this->dm->getClassMetadata($documentName);
            $this->persisters[$documentName] = new Persisters\BasicDocumentPersister($this->dm, $class);
        }
        return $this->persisters[$documentName];
    }

    /**
     * Gets a collection persister for a collection-valued association.
     *
     * @param AssociationMapping $association
     * @return Doctrine\ODM\CouchDB\Persisters\AbstractCollectionPersister
     */
    public function getCollectionPersister(array $association)
    {
        $type = $association['type'];
        if ( ! isset($this->collectionPersisters[$type])) {
            if ($type == ClassMetadata::ONE_TO_MANY) {
                $persister = new Persisters\OneToManyPersister($this->dm);
            } else if ($type == ClassMetadata::MANY_TO_MANY) {
                $persister = new Persisters\ManyToManyPersister($this->dm);
            }
            $this->collectionPersisters[$type] = $persister;
        }
        return $this->collectionPersisters[$type];
    }
}