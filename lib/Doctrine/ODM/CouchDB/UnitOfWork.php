<?php

namespace Doctrine\ODM\CouchDB;

class UnitOfWork
{
    const STATE_NEW = 1;
    const STATE_MANAGED = 2;
    const STATE_REMOVED = 3;

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

    /**
     * @var array
     */
    private $scheduledInsertions = array();

    private $documentState = array();

    /**
     * @var array
     */
    private $scheduledUpdates = array();

    /**
     * @var array
     */
    private $scheduledRemovals = array();

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

        $this->registerManaged($doc, null);

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

    public function scheduleInsert($object)
    {
        if ($this->getDocumentState($object) != self::STATE_NEW) {
            throw new \Exception("Object is already managed!");
        }

        $cm = $this->dm->getClassMetadata(get_class($object));

        if ($cm->idGenerator == Mapping\ClassMetadata::IDGENERATOR_ASSIGNED) {
            $id = $cm->getIdentifierValues($object);
            if (!$id) {
                throw new \Exception("no id");
            }
        }

        $oid = \spl_object_hash($object);
        $this->scheduledInsertions[$oid] = $object;
    }

    public function scheduleRemove($object)
    {
        $oid = \spl_object_hash($object);
        $this->scheduledInsertions[$oid] = $object;
    }

    public function getDocumentState($object)
    {
        $oid = \spl_object_hash($object);
        if (isset($this->documentState[$oid])) {
            return $this->documentState[$oid];
        }
        return self::STATE_NEW;
    }

    public function flush()
    {
        foreach ($this->scheduledInsertions AS $entity) {
            
        }
    }

    public function registerManaged($document, $identifier)
    {
        $this->documentState[spl_object_hash($document)] = self::STATE_MANAGED;
    }

    /**
     * Tries to find an entity with the given identifier in the identity map of
     * this UnitOfWork.
     *
     * @param mixed $id The entity identifier to look for.
     * @param string $rootClassName The name of the root class of the mapped entity hierarchy.
     * @return mixed Returns the entity with the specified identifier if it exists in
     *               this UnitOfWork, FALSE otherwise.
     */
    public function tryGetById($id, $rootClassName)
    {
        $idHash = implode(' ', (array) $id);
        if (isset($this->identityMap[$rootClassName][$idHash])) {
            return $this->identityMap[$rootClassName][$idHash];
        }
        return false;
    }
}