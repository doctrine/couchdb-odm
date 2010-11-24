<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Types\Type;

/**
 * Unit of work class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class UnitOfWork
{
    const STATE_NEW = 1;
    const STATE_MANAGED = 2;
    const STATE_REMOVED = 3;
    const STATE_DETACHED = 4;

    /**
     * @var DocumentManager
     */
    private $dm = null;

    /**
     * @var array
     */
    private $identityMap = array();

    /**
     * @var array
     */
    private $documentIdentifiers = array();

    /**
     * @var array
     */
    private $documentRevisions = array();

    /**
     * @var array
     */
    private $documentState = array();

    /**
     * CouchDB always returns and updates the whole data of a document. If on update data is "missing"
     * this means the data is deleted. This also applies to attachments. This is why we need to ensure
     * that data that is not mapped is not lost. This map here saves all the "left-over" data and keeps
     * track of it if necessary.
     *
     * @var array
     */
    private $nonMappedData = array();

    /**
     * @var array
     */
    private $originalData = array();

    /**
     * @var array
     */
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
    private $visitedCollections = array();

    /**
     * @var array
     */
    private $idGenerators = array();

    /**
     * @var EventManager
     */
    private $evm;

    /**
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        $this->evm = $dm->getEventManager();
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
        if (isset($data['doctrine_metadata']['type'])) {
             $type = $data['doctrine_metadata']['type'];
             if (isset($documentName) && $this->dm->getConfiguration()->getValidateDoctrineMetadata()) {
                $validate = true;
             }
        } else if (isset($documentName)) {
             $type = $documentName;
             if ($this->dm->getConfiguration()->getWriteDoctrineMetadata()) {
                $data['doctrine_metadata'] = array('type' => $documentName);
             }
        } else {
             throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }

        $class = $this->dm->getClassMetadata($type);

        $documentState = array();
        $nonMappedData = array();
        $id = $data['_id'];
        $rev = $data['_rev'];
        $conflict = false;
        foreach ($data as $jsonName => $jsonValue) {
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    if ($jsonValue === null) {
                        $documentState[$class->fieldMappings[$fieldName]['fieldName']] = null;
                    } else {
                        $documentState[$class->fieldMappings[$fieldName]['fieldName']] =
                            Type::getType($class->fieldMappings[$fieldName]['type'])
                                ->convertToPHPValue($jsonValue);
                    }
                }
            } else if ($jsonName == 'doctrine_metadata') {
                if (!isset($jsonValue['associations'])) {
                    continue;
                }

                foreach ($jsonValue['associations'] AS $assocName => $assocValue) {
                    if (isset($class->associationsMappings[$assocName])) {
                        if ($class->associationsMappings[$assocName]['type'] & ClassMetadata::TO_ONE) {
                            if ($assocValue) {
                                $assocValue = $this->dm->getReference($class->associationsMappings[$assocName]['targetDocument'], $assocValue);
                            }
                            $documentState[$class->associationsMappings[$assocName]['fieldName']] = $assocValue;
                        } else if ($class->associationsMappings[$assocName]['type'] & ClassMetadata::MANY_TO_MANY) {
                            if ($class->associationsMappings[$assocName]['isOwning']) {
                                $documentState[$class->associationsMappings[$assocName]['fieldName']] = new PersistentIdsCollection(
                                    new \Doctrine\Common\Collections\ArrayCollection(),
                                    $class->associationsMappings[$assocName]['targetDocument'],
                                    $this->dm,
                                    $assocValue
                                );
                            }
                        }
                    }
                }
            } else if ($jsonName == '_rev') {
                continue;
            } else if ($jsonName == '_conflicts') {
                $conflict = true;
            } else if ($class->hasAttachments && $jsonName == '_attachments') {
                $documentState[$class->attachmentField] = $this->createDocumentAttachments($id, $jsonValue);
            } else {
                $nonMappedData[$jsonName] = $jsonValue;
            }
        }

        if ($conflict && $this->evm->hasListeners(Event::onConflict)) {
            // there is a conflict and we have an event handler that might resolve it
            $this->evm->dispatchEvent(Event::onConflict, new Events\ConflictEventArgs($data, $this->dm, $documentName));
            // the event might be resolved in the couch now, load it again:
            return $this->dm->find($documentName, $id);
        }

        // initialize inverse side collections
        foreach ($class->associationsMappings AS $assocName => $assocOptions) {
            if (!$assocOptions['isOwning'] && $assocOptions['type'] & ClassMetadata::TO_MANY) {
                $documentState[$class->associationsMappings[$assocName]['fieldName']] = new PersistentViewCollection(
                    new \Doctrine\Common\Collections\ArrayCollection(),
                    $this->dm,
                    $id,
                    $class->associationsMappings[$assocName]['mappedBy']
                );
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

        if (isset($validate) && !($document instanceof $documentName)) {
            throw new \InvalidArgumentException("Doctrine metadata mismatch! Requested type '$documentName' type does not match type '$type' stored in the metdata");
        }

        if ($overrideLocalValues) {
            $this->nonMappedData[$oid] = $nonMappedData;
            foreach ($class->reflFields as $prop => $reflFields) {
                $value = isset($documentState[$prop]) ? $documentState[$prop] : null;
                $reflFields->setValue($document, $value);
                $this->originalData[$oid][$prop] = $value;
            }
        }

        if ($this->evm->hasListeners(Event::postLoad)) {
            $this->evm->dispatchEvent(Event::postLoad, new Events\LifecycleEventArgs($document, $this->dm));
        }

        return $document;
    }

    /**
     * @param  string $documentId
     * @param  array $data
     * @return array
     */
    private function createDocumentAttachments($documentId, $data)
    {
        $attachments = array();

        $client = $this->dm->getConfiguration()->getHttpClient();
        $basePath = '/' . $this->dm->getConfiguration()->getDatabase() . '/' . $documentId . '/';
        foreach ($data AS $filename => $attachment) {
            if (isset($attachment['stub']) && $attachment['stub']) {
                $instance = Attachment::createStub($attachment['content_type'], $attachment['length'], $attachment['revpos'], $client, $basePath . $filename);
            } else if (isset($attachment['data'])) {
                $instance = Attachment::createFromBase64Data($attachment['data'], $attachment['content_type'], $attachment['revpos']);
            }

            $attachments[$filename] = $instance;
        }

        return $attachments;
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
     * Schedule insertion of this document and cascade if neccessary.
     * 
     * @param object $document
     */
    public function scheduleInsert($document)
    {
        $visited = array();
        $this->doScheduleInsert($document, $visited);
    }

    private function doScheduleInsert($document, &$visited)
    {
        $oid = \spl_object_hash($document);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = true;

        $class = $this->dm->getClassMetadata(get_class($document));
        $state = $this->getDocumentState($document);
        
        switch ($state) {
            case self::STATE_NEW:
                $id = $this->getIdGenerator($class->idGenerator)->generate($document, $class, $this->dm);

                $this->registerManaged($document, $id, null);

                if ($this->evm->hasListeners(Event::prePersist)) {
                    $this->evm->dispatchEvent(Event::prePersist, new Events\LifecycleEventArgs($document, $this->dm));
                }
                break;
            case self::STATE_MANAGED:
                // TODO: Change Tracking Deferred Explicit
                break;
            case self::STATE_REMOVED:
                // document becomes managed again
                unset($this->scheduledRemovals[$oid]);
                $this->documentState[$oid] = self::STATE_MANAGED;
                break;
            case self::STATE_DETACHED:
                throw new \InvalidArgumentException("Detached entity passed to persist().");
                break;
        }

        $this->cascadeScheduleInsert($class, $document, $visited);
    }

    /**
     *
     * @param ClassMetadata $class
     * @param object $document
     * @param array $visited
     */
    private function cascadeScheduleInsert($class, $document, &$visited)
    {
        foreach ($class->associationsMappings AS $assocName => $assoc) {
            if ( ($assoc['cascade'] & ClassMetadata::CASCADE_PERSIST) ) {
                $related = $class->reflFields[$assocName]->getValue($document);
                if ($class->associationsMappings[$assocName]['type'] & ClassMetadata::TO_ONE) {
                    if ($this->getDocumentState($related) == self::STATE_NEW) {
                        $this->doScheduleInsert($related, $visited);
                    }
                } else {
                    // $related can never be a persistent collection in case of a new entity.
                    foreach ($related AS $relatedDocument) {
                        if ($this->getDocumentState($relatedDocument) == self::STATE_NEW) {
                            $this->doScheduleInsert($relatedDocument, $visited);
                        }
                    }
                }
            }
        }
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
        $this->documentState[$oid] = self::STATE_REMOVED;

        if ($this->evm->hasListeners(Event::preRemove)) {
            $this->evm->dispatchEvent(Event::preRemove, new Events\LifecycleEventArgs($document, $this->dm));
        }
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

    /**
     * @param ClassMetadata $class
     * @param object $document
     * @return void
     */
    public function computeChangeSet(ClassMetadata $class, $document)
    {
        if ($document instanceof Proxy\Proxy && !$document->__isInitialized__) {
            return;
        }

        $oid = \spl_object_hash($document);
        $actualData = array();
        // TODO: Do we need two loops?
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
                    if (!$class->associationsMappings[$fieldName]['isOwning']) {
                        continue;
                    }

                    if ( ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) && $this->originalData[$oid][$fieldName] !== $fieldValue) {
                        $changed = true;
                        break;
                    } else if ( ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_MANY)) {
                        if ( !($fieldValue instanceof PersistentCollection)) {
                            // if its not a persistent collection and the original value changed. otherwise it could just be null
                            $changed = true;
                            break;
                        } else if ($fieldValue->changed()) {
                            $this->visitedCollections[] = $fieldValue;
                            $changed = true;
                            break;
                        }
                    }
                } else if ($class->hasAttachments && $fieldName == $class->attachmentField) {
                    // array of value objects, can compare that stricly
                    if ($this->originalData[$oid][$fieldName] !== $fieldValue) {
                        $changed = true;
                        break;
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

    /**
     * Flush Operation - Write all dirty entries to the CouchDB.
     *
     * @return void
     */
    public function flush()
    {
        $this->detectChangedDocuments();

        if ($this->evm->hasListeners(Event::onFlush)) {
            $this->evm->dispatchEvent(Event::onFlush, new Events\OnFlushEventArgs($this));
        }

        $config = $this->dm->getConfiguration();

        $useDoctrineMetadata = $config->getWriteDoctrineMetadata();

        $bulkUpdater = $this->dm->getCouchDBClient()->createBulkUpdater();
        $bulkUpdater->setAllOrNothing($config->getAllOrNothingFlush());

        foreach ($this->scheduledRemovals AS $oid => $document) {
            $bulkUpdater->deleteDocument($this->documentIdentifiers[$oid], $this->documentRevisions[$oid]);
            $this->removeFromIdentityMap($document);

            if ($this->evm->hasListeners(Event::postRemove)) {
                $this->evm->dispatchEvent(Event::postRemove, new Events\LifecycleEventArgs($document, $this->dm));
            }
        }

        foreach ($this->scheduledUpdates AS $oid => $document) {
            $class = $this->dm->getClassMetadata(get_class($document));

            if ($this->evm->hasListeners(Event::preUpdate)) {
                $this->evm->dispatchEvent(Event::preUpdate, new Events\LifecycleEventArgs($document, $this->dm));
                $this->computeChangeSet($class, $document); // TODO: prevent association computations in this case?
            }

            $data = array();
            if ($useDoctrineMetadata) {
                $data['doctrine_metadata'] = array('type' => $class->name);
            }

            // Convert field values to json values.
            foreach ($this->documentChangesets[$oid] AS $fieldName => $fieldValue) {
                if (isset($class->fieldMappings[$fieldName])) {
                    if ($fieldValue !== null) {
                        $fieldValue = Type::getType($class->fieldMappings[$fieldName]['type'])
                            ->convertToCouchDBValue($fieldValue);
                    }

                    $data[$class->fieldMappings[$fieldName]['jsonName']] = $fieldValue;

                } else if (isset($class->associationsMappings[$fieldName]) && $useDoctrineMetadata) {
                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if (\is_object($fieldValue)) {
                            $data['doctrine_metadata']['associations'][$fieldName] = $this->getDocumentIdentifier($fieldValue);
                        } else {
                            $data['doctrine_metadata']['associations'][$fieldName] = null;
                        }
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // TODO: Optimize when not initialized yet! In ManyToMany case we can keep track of ALL ids
                            $ids = array();
                            if (is_array($fieldValue) || $fieldValue instanceof \Doctrine\Common\Collections\Collection) {
                                foreach ($fieldValue AS $relatedObject) {
                                    $ids[] = $this->getDocumentIdentifier($relatedObject);
                                }
                            }

                            $data['doctrine_metadata']['associations'][$fieldName] = $ids;
                        }
                    }
                } else if ($class->hasAttachments && $fieldName == $class->attachmentField) {
                    if (is_array($fieldValue) && $fieldValue) {
                        $data['_attachments'] = array();
                        foreach ($fieldValue AS $filename => $attachment) {
                            if (!($attachment instanceof \Doctrine\ODM\CouchDB\Attachment)) {
                                throw CouchDBException::invalidAttachment($class->name, $this->documentIdentifiers[$oid], $filename);
                            }
                            $data['_attachments'][$filename] = $attachment->toArray();
                        }
                    }
                }
            }

            // respect the non mapped data, otherwise they will be deleted.
            if (isset($this->nonMappedData[$oid]) && $this->nonMappedData[$oid]) {
                $data = array_merge($data, $this->nonMappedData[$oid]);
            }

            $rev = $this->getDocumentRevision($document);
            if ($rev) {
                $data['_rev'] = $rev;
            }
            $bulkUpdater->updateDocument($data);
        }
        $response = $bulkUpdater->execute();
        $updateConflictDocuments = array();
        if ($response->status == 201) {
            foreach ($response->body AS $docResponse) {
                if (!isset($this->identityMap[$docResponse['id']])) {
                    // deletions
                    continue;
                }

                $document = $this->identityMap[$docResponse['id']];
                if (isset($docResponse['error'])) {
                    $updateConflictDocuments[] = $document;
                } else {
                    $this->documentRevisions[spl_object_hash($document)] = $docResponse['rev'];
                    $class = $this->dm->getClassMetadata(get_class($document));
                    if ($class->isVersioned) {
                        $class->reflFields[$class->versionField]->setValue($document, $docResponse['rev']);
                    }
                }

                if ($this->evm->hasListeners(Event::postUpdate)) {
                    $this->evm->dispatchEvent(Event::postUpdate, new Events\LifecycleEventArgs($document, $this->dm));
                }
            }
        }

        foreach ($this->visitedCollections AS $col) {
            $col->takeSnapshot();
        }

        $this->scheduledUpdates =
        $this->scheduledRemovals =
        $this->visitedCollections = array();

        if (count($updateConflictDocuments)) {
            throw new UpdateConflictException($updateConflictDocuments);
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

    /**
     * @param  object $document
     * @return bool
     */
    public function contains($document)
    {
        return isset($this->documentIdentifiers[\spl_object_hash($document)]);
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
