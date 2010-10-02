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

    /**
     * @var array
     */
    private $idGenerators = array();

    /**
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    /**
     * Create a document given class, data and the doc-id and revision
     * 
     * @param string $documentName
     * @param array $documentState
     * @param array $hints
     * @return object
     */
    public function createDocument($documentName, $data, array &$hints = array())
    {
        if (isset($data['doctrine_metadata'])) {
            $type = $data['doctrine_metadata']['type'];
            if (isset($documentName) && $this->dm->getConfiguration()->getValidateDoctrineMetadata()) {
                // TODO implement type validation
            }
        } elseif(isset($documentName)) {
            $type = $documentName;
            if ($this->dm->getConfiguration()->getWriteDoctrineMetadata()) {
                // TODO automatically add metadata
            }
        } else {
            throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }

        $class = $this->dm->getClassMetadata($type);

        $documentState = array();
        $id = $data['_id'];
        $rev = $data['_rev'];
        foreach ($data as $jsonName => $value) {
            // TODO: For migrations and stuff, maybe there should really be a "rest" field?
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    $documentState[$class->fieldMappings[$fieldName]['fieldName']] = $value;
                } else if (isset($class->associationsMappings[$fieldName])) {

                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if ($value) {
                            $value = $this->dm->getReference($class->associationsMappings[$fieldName]['targetDocument'], $value);
                        }
                        $documentState[$class->associationsMappings[$fieldName]['fieldName']] = $value;
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::MANY_TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // 1. if owning side we know all the ids
                            $documentState[$class->associationsMappings[$fieldName]['fieldName']] = new \Doctrine\ODM\CouchDB\PersistentIdsCollection(
                                new \Doctrine\Common\Collections\ArrayCollection(),
                                $class->associationsMappings[$fieldName]['targetDocument'],
                                $this->dm,
                                $value
                            );
                        } else {
                            // 2. if inverse side we need to nest the lazy loading relations view
                            // TODO implement inverse side lazy loading
                        }
                    }
                }
            }
        }

        if (isset($this->identityMap[$id])) {
            $document = $this->identityMap[$id];
            $overrideLocalValues = false;

            if ( ($document instanceof Proxy && !$document->__isInitialized__) || isset($hints['refresh'])) {
                $overrideLocalValues = true;
                $oid = spl_object_hash($document);
            }
        } else {
            $document = $class->newInstance();
            $this->identityMap[$id] = $document;

            $oid = spl_object_hash($document);
            $this->documentState[$oid] = self::STATE_MANAGED;
            $this->documentIdentifiers[$oid] = $id;
            $this->documentRevisions[$oid] = $rev;
            $overrideLocalValues = true;
        }

        if ($overrideLocalValues) {
            foreach ($class->reflFields as $prop => $reflFields) {
                $value = isset($documentState[$prop]) ? $documentState[$prop] : null;
                $reflFields->setValue($document, $value);
                $this->originalData[$oid][$prop] = $value;
            }
        }

        return $document;
    }

    /**
     * @param  object $document
     * @return array
     */
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

        $id = $this->getIdGenerator($class->idGenerator)->generate($document, $class, $this->dm);

        $this->registerManaged($document, $id, null);
    }

    private function getIdGenerator($type)
    {
        if (!isset($this->idGenerators[$type])) {
            $this->idGenerators[$type] = Id\IdGenerator::create($type);
        }
        return $this->idGenerators[$type];
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
            $this->scheduledUpdates[$oid] = $document;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data

            $changed = false;
            foreach ($actualData AS $fieldName => $fieldValue) {
                if (isset($class->fieldMappings[$fieldName]) && $this->originalData[$oid][$fieldName] !== $fieldValue) {
                    $changed = true;
                    break;
                } else if(isset($class->associationsMappings[$fieldName])) {
                    if ( ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) && $this->originalData[$oid][$fieldName] !== $fieldValue) {
                        $changed = true;
                        break;
                    } else if ( ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE)) {
                        if ( !($fieldValue instanceof PersistentCollection) || $fieldValue->changed()) {
                            $changed = true;
                            break;
                        }
                    }
                }
            }

            if ($changed) {
                $this->documentChangesets[$oid] = $actualData;
                $this->scheduledUpdates[$oid] = $document;
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

        $bulkUpdater = new Persisters\BulkUpdater($this->dm->getConfiguration()->getHttpClient(), $this->dm->getConfiguration()->getDatabase());
        $bulkUpdater->setAllOrNothing(true); // TODO: Docs discourage this, but in the UoW context it makes sense? Evaluate!

        foreach ($this->scheduledRemovals AS $oid => $document) {
            $bulkUpdater->deleteDocument($this->documentIdentifiers[$oid], $this->documentRevisions[$oid]);
            $this->removeFromIdentityMap($document);
        }

        foreach ($this->scheduledUpdates AS $oid => $document) {
            $data = array();
            $class = $this->dm->getClassMetadata(get_class($document));

            // Convert field values to json values.
            foreach ($this->documentChangesets[$oid] AS $fieldName => $fieldValue) {
                if (isset($class->fieldMappings[$fieldName])) {
                    $data[$class->fieldMappings[$fieldName]['jsonName']] = $fieldValue;
                } else if (isset($class->associationsMappings[$fieldName])) {
                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if (\is_object($fieldValue)) {
                            $data[$fieldName] = $this->getDocumentIdentifier($fieldValue);
                        } else {
                            $data[$fieldName] = null;
                        }
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // TODO: Optimize when not initialized yet! In ManyToMany case we can keep track of ALL ids
                            $ids = array();
                            foreach ($fieldValue AS $relatedObject) {
                                $ids[] = $this->getDocumentIdentifier($relatedObject);
                            }

                            $data[$fieldName] = $ids;
                        }
                    }
                }
            }

            if ($this->dm->getConfiguration()->getWriteDoctrineMetadata()) {
                $data['doctrine_metadata'] = $this->dm->getDoctrineMetadata(get_class($document));
            }

            $rev = $this->getDocumentRevision($document);
            if ($rev) {
                $data['_rev'] = $rev;
            }
            $bulkUpdater->updateDocument($data);
        }
        $response = $bulkUpdater->execute();
        $errors = array();
        if ($response->status == 201) {
            foreach ($response->body AS $docResponse) {
                if (!isset($this->identityMap[$docResponse['id']])) {
                    // deletions
                    continue;
                }

                $document = $this->identityMap[$docResponse['id']];
                if (isset($docResponse['error'])) {
                    $errors[] = $docResponse;
                } else {
                    $this->documentRevisions[spl_object_hash($document)] = $docResponse['rev'];
                    $class = $this->dm->getClassMetadata(get_class($document));
                    if ($class->isVersioned) {
                        $class->reflFields[$class->versionField]->setValue($document, $docResponse['rev']);
                    }
                }
            }
        }
        
        $this->scheduledUpdates =
        $this->scheduledRemovals = array();

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
