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
            foreach ($metadata->reflFields as $prop => $reflFields) {
                $value = isset($data[$prop]) ? $data[$prop] : null;
                $reflFields->setValue($doc, $value);
                $this->originalData[$oid][$prop] = $value;
            }
        }

        return $doc;
    }

    /**
     * @Internal 
     * @param <type> $oid
     * @param <type> $rev
     */
    public function setDocumentRevision($oid, $rev)
    {
        $this->documentRevisions[$oid] = $rev;
    }

    public function getOriginalData($document)
    {
        return $this->originalData[\spl_object_hash($document)];
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

    public function scheduleInsert($document)
    {
        if ($this->getDocumentState($document) != self::STATE_NEW) {
            throw new \Exception("Object is already managed!");
        }

        $class = $this->dm->getClassMetadata(get_class($document));

        $id = Id\IdGenerator::get($class->idGenerator)->generate($document, $class, $this->dm);

        $this->registerManaged($document, $id, null);
    }

    public function scheduleRemove($document)
    {
        $oid = \spl_object_hash($document);
        $this->scheduledRemovals[$oid] = $document;
    }

    public function getDocumentState($document)
    {
        $oid = \spl_object_hash($document);
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
        if ($document instanceof Proxy\Proxy && !$document->__isInitialized__) {
            return;
        }

        $oid = \spl_object_hash($document);
        $actualData = array();
        foreach ($class->reflFields AS $propName => $reflProp) {
            $actualData[$propName] = $reflProp->getValue($document);
            // TODO: ORM transforms arrays and collections into persistent collections
        }
        // unset the revision field if necessary, it is not to be managed by the user in write scenarios.
        if ($class->isVersioned) {
            unset($actualData[$class->versionField]);
        }

        if (!isset($this->originalData[$oid])) {
            // Entity is New and should be inserted
            $this->originalData[$oid] = $actualData;

            $this->documentChangesets[$oid] = $actualData;
            $this->scheduledUpdates[] = $document;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data

            $changed = false;
            foreach ($actualData AS $k => $v) {
                if (isset($class->fieldMappings[$k]) && $this->originalData[$oid][$k] !== $v) {
                    $changed = true;
                    break;
                } else if(isset($class->associationsMappings[$k])) {
                    if ( ($class->associationsMappings[$k]['type'] & ClassMetadata::TO_ONE) && $this->originalData[$oid][$k] !== $v) {
                        $changed = true;
                        break;
                    } else if ( ($class->associationsMappings[$k]['type'] & ClassMetadata::TO_ONE)) {
                        if ( !($v instanceof PersistentCollection) || $v->changed()) {
                            $changed = true;
                            break;
                        }
                    }
                }
            }

            if ($changed) {
                $this->documentChangesets[$oid] = $actualData;
                $this->scheduledUpdates[] = $document;
            }
        }
    }

    /**
     * Gets the changeset for an document.
     *
     * @return array
     */
    public function getDocumentChangeSet($document)
    {
        $oid = spl_object_hash($document);
        if (isset($this->documentChangesets[$oid])) {
            return $this->documentChangesets[$oid];
        }
        return array();
    }

    public function flush()
    {
        $this->detectChangedDocuments();

        $persister = $this->getDocumentPersister();
        $errors = array();

        foreach ($this->scheduledRemovals AS $key => $document) {
            $persister->delete($document);
            unset($this->scheduledRemovals[$key]);
            $this->removeFromIdentityMap($document);
        }

        foreach ($this->scheduledUpdates AS $key => $document) {
            $persister->addInsert($document);
            unset($this->scheduledUpdates[$key]);
        }

        $errors = $persister->executeInserts();

        if (count($errors)) {
            throw new \Exception("Errors happend: " . count($errors));
        }
    }

    /**
     * INTERNAL:
     * Removes an document from the identity map. This effectively detaches the
     * document from the persistence management of Doctrine.
     *
     * @ignore
     * @param object $document
     * @return boolean
     */
    public function removeFromIdentityMap($document)
    {
        $oid = spl_object_hash($document);

        if (isset($this->identityMap[$this->documentIdentifiers[$oid]])) {
            unset($this->identityMap[$this->documentIdentifiers[$oid]],
                  $this->documentIdentifiers[$oid],
                  $this->documentRevisions[$oid],
                  $this->documentState[$oid]);

            return true;
        }

        return false;
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
     * @param  object $document
     * @return string
     */
    public function getDocumentRevision($document)
    {
        $oid = \spl_object_hash($document);
        if (isset($this->documentRevisions[$oid])) {
            return $this->documentRevisions[$oid];
        }
        return null;
    }

    public function getDocumentIdentifier($document)
    {
        $oid = \spl_object_hash($document);
        if (isset($this->documentIdentifiers[$oid])) {
            return $this->documentIdentifiers[$oid];
        } else {
            throw new CouchDBException("Document is not managed and has no identifier.");
        }
    }
}
