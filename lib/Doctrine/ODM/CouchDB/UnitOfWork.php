<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

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
    private $persister = null;

    /**
     * The collection persister instances used to persist collections.
     *
     * @var array
     */
    private $collectionPersisters = array();

    /**
     * @var array
     */
    private $documentIdentifiers = array();

    /**
     * @var array
     */
    private $documentRevisions = array();

    private $documentState = array();

    private $originalData = array();

    private $documentChangesets = array();

    /**
     * Contrary to the ORM, CouchDB only knows "updates". The question is wheater a revion exists (Real update vs insert).
     *
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

    /**
     * Create a document given class, data and the doc-id and revision
     * @param string $class
     * @param array $data
     * @param string $id
     * @param string $rev
     * @param array $hints
     * @return object
     */
    public function createDocument($class, $data, $id, $rev = null, array &$hints = array())
    {
        $metadata = $this->dm->getClassMetadata($class);

        if (isset($this->identityMap[$id])) {
            $doc = $this->identityMap[$id];
            $overrideLocalValues = false;

            if ( ($doc instanceof Proxy && !$doc->__isInitialized__) || isset($hints['refresh'])) {
                $overrideLocalValues = true;
                $oid = spl_object_hash($doc);
            }
        } else {
            $doc = $metadata->newInstance();
            $this->identityMap[$id] = $doc;

            $oid = spl_object_hash($doc);
            $this->documentState[$oid] = self::STATE_MANAGED;
            $this->documentIdentifiers[$oid] = $id;
            $this->documentRevisions[$oid] = $rev;
            $overrideLocalValues = true;
        }

        if ($overrideLocalValues) {
            foreach ($metadata->reflProps AS $prop => $reflProp) {
                /* @var $reflProp ReflectionProperty */
                $value = $data[$prop];
                $reflProp->setValue($doc, $value);
                $this->originalData[$oid][$prop] = $value;
            }
        }

        return $doc;
    }

    public function getOriginalData($object)
    {
        return $this->originalData[\spl_object_hash($object)];
    }

    /**
     * Gets the DocumentPersister for an Entity.
     *
     * @return Doctrine\ODM\CouchDB\Persisters\BasicDocumentPersister
     */
    public function getDocumentPersister()
    {
        // TODO do we need to support multiple persister?
        if ($this->persister === null) {
            $this->persister = new Persisters\BasicDocumentPersister($this->dm);
        }
        return $this->persister;
    }

    public function scheduleInsert($object)
    {
        if ($this->getDocumentState($object) != self::STATE_NEW) {
            throw new \Exception("Object is already managed!");
        }

        $class = $this->dm->getClassMetadata(get_class($object));

        $id = Id\IdGenerator::get($class->idGenerator)->generate($object, $class, $this->dm);

        $this->registerManaged($object, $id, null);
    }

    public function scheduleRemove($object)
    {
        $oid = \spl_object_hash($object);
        $this->scheduledRemovals[$oid] = $object;
    }

    public function getDocumentState($object)
    {
        $oid = \spl_object_hash($object);
        if (isset($this->documentState[$oid])) {
            return $this->documentState[$oid];
        }
        return self::STATE_NEW;
    }

    private function detectChangedDocuments()
    {
        foreach ($this->identityMap AS $id => $document) {
            $state = $this->getDocumentState($document);
            if ($state == self::STATE_MANAGED) {
                $class = $this->dm->getClassMetadata(get_class($document));
                $this->computeChangeSet($class, $document);
            }
        }
    }

    public function computeChangeSet(ClassMetadata $class, $document)
    {
        $oid = \spl_object_hash($document);
        $actualData = array();
        foreach ($class->reflProps AS $propName => $reflProp) {
            $actualData[$propName] = $reflProp->getValue($document);
            // TODO: ORM transforms arrays and collections into persistent collections
        }

        if (!isset($this->originalData[$oid])) {
            // Entity is New and should be inserted
            $this->originalData[$oid] = $actualData;

            $this->documentChangesets[$oid] = $actualData;
            $this->scheduledUpdates[] = $document;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data

            if (array_diff($actualData, $this->originalData[$oid])) {
                $this->documentChangesets[$oid] = $actualData;
                $this->scheduledUpdates[] = $document;
            }
        }
    }

    public function flush()
    {
        $this->detectChangedDocuments();

        // TODO move all interactions with CouchDB to the persister, see issue #1
        /* @var $client CouchDBClient */
        $couchClient = $this->dm->getCouchDBClient();
        $errors = array();

        foreach ($this->scheduledRemovals AS $document) {
            $oid = spl_object_hash($document);
            $response = $couchClient->deleteDocument($this->documentIdentifiers[$oid], $this->documentRevisions[$oid]);

            if ($response->status == 200) {
                unset($this->identityMap[$this->documentIdentifiers[$oid]],
                      $this->documentIdentifiers[$oid],
                      $this->documentRevisions[$oid],
                      $this->documentState[$oid]);
            }
        }
        foreach ($this->scheduledUpdates AS $document) {
            $oid = spl_object_hash($document);
            $data = array();
            $class = $this->dm->getClassMetadata(get_class($document));
            foreach ($this->documentChangesets[$oid] AS $k => $v) {
                $data[$class->properties[$k]['resultkey']] = $v;
            }
            $data['doctrine_metadata'] = array('type' => get_class($document));

            if (isset($this->documentRevisions[$oid])) {
                $response = $couchClient->putDocument($data, $this->documentIdentifiers[$oid], $this->documentRevisions[$oid]);
            } else {
                $response = $couchClient->postDocument($data);
            }

            if ( ($response->status === 200 || $response->status == 201) && $response->body['ok'] == true) {
                $this->documentRevisions[$oid] = $response->body['rev'];
            } else {
                $errors[] = $document;
            }
        }

        if (count($errors)) {
            throw new \Exception("Errors happend: " . count($errors));
        }
    }

    public function registerManaged($document, $identifier, $revision)
    {
        $oid = spl_object_hash($document);
        $this->documentState[$oid] = self::STATE_MANAGED;
        $this->documentIdentifiers[$oid] = $identifier;
        $this->documentRevisions[$oid] = $revision;
        $this->identityMap[$identifier] = $document;
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
    public function tryGetById($id)
    {
        if (isset($this->identityMap[$id])) {
            return $this->identityMap[$id];
        }
        return false;
    }

    /**
     * Get the CouchDB revision of the document that was current upon retrieval.
     *
     * @throws CouchDBException
     * @param  object $object
     * @return string
     */
    public function getDocumentRevision($object)
    {
        $oid = \spl_object_hash($object);
        if (array_key_exists($oid, $this->documentRevisions)) {
            return $this->documentRevisions[$oid];
        } else {
            throw new CouchDBException("Document is not managed and has no revision.");
        }
    }

    public function getDocumentIdentifier($object)
    {
        $oid = \spl_object_hash($object);
        if (isset($this->documentIdentifiers[$oid])) {
            return $this->documentIdentifiers[$oid];
        } else {
            throw new CouchDBException("Document is not managed and has no identifier.");
        }
    }
}
