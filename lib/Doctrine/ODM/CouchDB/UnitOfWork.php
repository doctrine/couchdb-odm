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
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

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
     * There is no need for a differentiation between original and changeset data in CouchDB, since
     * updates have to be complete updates of the document (unless you are using an update handler, which
     * is not yet a feature of CouchDB ODM).
     *
     * @var array
     */
    private $originalData = array();

    /**
     * The original data of embedded document handled separetly from simple property mapping data.
     * 
     * @var array
     */
    private $originalEmbeddedData = array();

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
     * @var MetadataResolver
     */
    private $metadataResolver;

    /**
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        $this->evm = $dm->getEventManager();
        $this->metadataResolver = $dm->getConfiguration()->getMetadataResolverImpl();

        $this->embeddedSerializer = new Mapping\EmbeddedDocumentSerializer($this->dm->getMetadataFactory(), 
                                                                           $this->metadataResolver);
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
        if (!$this->metadataResolver->canMapDocument($data)) {
            throw new \InvalidArgumentException("Missing or missmatching metadata description in the Document, cannot hydrate!");
        }

        $type = $this->metadataResolver->getDocumentType($data);
        $class = $this->dm->getClassMetadata($type);

        $documentState = array();
        $nonMappedData = array();
        $embeddedDocumentState = array();

        $id = $data['_id'];
        $rev = $data['_rev'];
        $conflict = false;
        foreach ($data as $jsonName => $jsonValue) {
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    if ($jsonValue === null) {
                        $documentState[$class->fieldMappings[$fieldName]['fieldName']] = null;
                    } else if (isset($class->fieldMappings[$fieldName]['embedded'])) {

                        $embeddedInstance = 
                            $this->embeddedSerializer->createEmbeddedDocument($jsonValue, $class->fieldMappings[$fieldName]);

                        $documentState[$jsonName] = $embeddedInstance;
                        // storing the jsonValue for embedded docs for now
                        $embeddedDocumentState[$jsonName] = $jsonValue;
                    } else {
                        $documentState[$class->fieldMappings[$fieldName]['fieldName']] =
                            Type::getType($class->fieldMappings[$fieldName]['type'])
                                ->convertToPHPValue($jsonValue);
                    }
                }
            } else if ($jsonName == '_rev') {
                continue;
            } else if ($jsonName == '_conflicts') {
                $conflict = true;
            } else if ($class->hasAttachments && $jsonName == '_attachments') {
                $documentState[$class->attachmentField] = $this->createDocumentAttachments($id, $jsonValue);
            } else if ($this->metadataResolver->canResolveJsonField($jsonName)) {
                $documentState = $this->metadataResolver->resolveJsonField($class, $this->dm, $documentState, $jsonName, $jsonValue);
            } else {
                $nonMappedData[$jsonName] = $jsonValue;
            }
        }

        if ($conflict && $this->evm->hasListeners(Event::onConflict)) {
            // there is a conflict and we have an event handler that might resolve it
            $this->evm->dispatchEvent(Event::onConflict, new Events\ConflictEventArgs($data, $this->dm, $type));
            // the event might be resolved in the couch now, load it again:
            return $this->dm->find($type, $id);
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

        if ($documentName && !($document instanceof $documentName)) {
            throw new InvalidDocumentTypeException($type, $documentName);
        }
        
        if ($overrideLocalValues) {
            $this->nonMappedData[$oid] = $nonMappedData;
            foreach ($class->reflFields as $prop => $reflFields) {
                $value = isset($documentState[$prop]) ? $documentState[$prop] : null;
                if (isset($embeddedDocumentState[$prop])) {
                    $this->originalEmbeddedData[$oid][$prop] = $embeddedDocumentState[$prop];
                } else {
                    $this->originalData[$oid][$prop] = $value;
                }
                $reflFields->setValue($document, $value);
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
                $this->persistNew($class, $document);
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
                if (!$related) {
                    continue;
                }

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
        $visited = array();
        $this->doRemove($document, $visited);
    }

    private function doRemove($document, &$visited)
    {
        $oid = \spl_object_hash($document);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = true;

        $this->scheduledRemovals[$oid] = $document;
        $this->documentState[$oid] = self::STATE_REMOVED;

        if ($this->evm->hasListeners(Event::preRemove)) {
            $this->evm->dispatchEvent(Event::preRemove, new Events\LifecycleEventArgs($document, $this->dm));
        }

        $this->cascadeRemove($document, $visited);
    }

    private function cascadeRemove($document, &$visited)
    {
        $class = $this->dm->getClassMetadata(get_class($document));
        foreach ($class->associationsMappings AS $name => $assoc) {
            if ($assoc['cascade'] & ClassMetadata::CASCADE_REMOVE) {
                $related = $class->reflFields[$assoc['fieldName']]->getValue($document);
                if ($related instanceof Collection || is_array($related)) {
                    // If its a PersistentCollection initialization is intended! No unwrap!
                    foreach ($related as $relatedDocument) {
                        $this->doRemove($relatedDocument, $visited);
                    }
                } else if ($related !== null) {
                    $this->doRemove($related, $visited);
                }
            }
        }
    }

    public function refresh($document)
    {
        $visited = array();
        $this->doRefresh($document, $visited);
    }

    private function doRefresh($document, &$visited)
    {
        $oid = \spl_object_hash($document);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = true;

        $response = $this->dm->getCouchDBClient()->findDocument($this->getDocumentIdentifier($document));

        if ($response->status == 404) {
            throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException();
        }

        $hints = array('refresh' => true);
        $this->createDocument($this->dm->getClassMetadata(get_class($document))->name, $response->body, $hints);

        $this->cascadeRefresh($document, $visited);
    }

    public function merge($document)
    {
        throw new \BadMethodCallException("Not yet implemented.");
    }

    public function detach($document)
    {
        throw new \BadMethodCallException("Not yet implemented.");
    }

    private function cascadeRefresh($document, &$visited)
    {
        $class = $this->dm->getClassMetadata(get_class($document));
        foreach ($class->associationsMappings as $assoc) {
            if ($assoc['cascade'] & ClassMetadata::CASCADE_REFRESH) {
                $related = $class->reflFields[$assoc['fieldName']]->getValue($document);
                if ($related instanceof Collection) {
                    if ($related instanceof PersistentCollection) {
                        // Unwrap so that foreach() does not initialize
                        $related = $related->unwrap();
                    }
                    foreach ($related as $relatedDocument) {
                        $this->doRefresh($relatedDocument, $visited);
                    }
                } else if ($related !== null) {
                    $this->doRefresh($related, $visited);
                }
            }
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
        $embeddedActualData = array();
        // 1. compute the actual values of the current document
        foreach ($class->reflFields AS $fieldName => $reflProperty) {
            $value = $reflProperty->getValue($document);
            if ($class->isCollectionValuedAssociation($fieldName) && $value !== null
                    && !($value instanceof PersistentCollection)) {

                if (!$value instanceof Collection) {
                    $value = new ArrayCollection($value);
                }

                if ($class->associationsMappings[$fieldName]['isOwning']) {
                    $coll = new PersistentIdsCollection(
                        $value,
                        $class->associationsMappings[$fieldName]['targetDocument'],
                        $this->dm,
                        array()
                    );
                } else {
                    $coll = new PersistentViewCollection(
                        $value,
                        $this->dm,
                        $this->documentIdentifiers[$oid],
                        $class->associationsMappings[$fieldName]['mappedBy']
                    );
                }

                $class->reflFields[$fieldName]->setValue($document, $coll);

                $actualData[$fieldName] = $coll;
            } else {
                $actualData[$fieldName] = $value;
                if (isset($class->fieldMappings[$fieldName]['embedded']) && $value !== null) {
                    // serializing embedded value right here, to be able to detect changes for later invocations
                    $embeddedActualData[$fieldName] = 
                        $this->embeddedSerializer->serializeEmbeddedDocument($value, $class->fieldMappings[$fieldName]);
                } 
            }
            // TODO: ORM transforms arrays and collections into persistent collections
        }
        // unset the revision field if necessary, it is not to be managed by the user in write scenarios.
        if ($class->isVersioned) {
            unset($actualData[$class->versionField]);
        }

        // 2. Compare to the original, or find out that this entity is new.
        if (!isset($this->originalData[$oid])) {
            // Entity is New and should be inserted
            $this->originalData[$oid] = $actualData;
            $this->scheduledUpdates[$oid] = $document;
            $this->originalEmbeddedData[$oid] = $embeddedActualData;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data

            $changed = false;
            foreach ($actualData AS $fieldName => $fieldValue) {
                // Important to not check embeded values here, because those are objects, equality check isn't enough
                // 
                if (isset($class->fieldMappings[$fieldName]) 
                    && !isset($class->fieldMappings[$fieldName]['embedded'])
                    && $this->originalData[$oid][$fieldName] !== $fieldValue) {
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

            // Check embedded documents here, only if there is no change yet
            if (!$changed) {
                foreach ($embeddedActualData as $fieldName => $fieldValue) {
                    if (!isset($this->originalEmbeddedData[$oid][$fieldName])
                        || $this->embeddedSerializer->isChanged(
                            $actualData[$fieldName],                        /* actual value */
                            $this->originalEmbeddedData[$oid][$fieldName],  /* original state  */
                            $class->fieldMappings[$fieldName]
                            )) {
                        $changed = true;
                        break;
                    }                    
                }
            }

            if ($changed) {
                $this->originalData[$oid] = $actualData;
                $this->scheduledUpdates[$oid] = $document;
                $this->originalEmbeddedData[$oid] = $embeddedActualData;
            }
        }

        // 3. check if any cascading needs to happen
        foreach ($class->associationsMappings AS $name => $assoc) {
            if ($this->originalData[$oid][$name]) {
                $this->computeAssociationChanges($assoc, $this->originalData[$oid][$name]);
            }
        }
    }

    /**
     * Computes the changes of an association.
     *
     * @param AssociationMapping $assoc
     * @param mixed $value The value of the association.
     */
    private function computeAssociationChanges($assoc, $value)
    {
        // Look through the entities, and in any of their associations, for transient (new)
        // enities, recursively. ("Persistence by reachability")
        if ($assoc['type'] & ClassMetadata::TO_ONE) {
            if ($value instanceof Proxy && ! $value->__isInitialized__) {
                return; // Ignore uninitialized proxy objects
            }
            $value = array($value);
        } else if ($value instanceof PersistentCollection) {
            // Unwrap. Uninitialized collections will simply be empty.
            $value = $value->unwrap();
        }

        $targetClass = $this->dm->getClassMetadata($assoc['targetDocument']);
        foreach ($value as $entry) {
            $state = $this->getDocumentState($entry);
            $oid = spl_object_hash($entry);
            if ($state == self::STATE_NEW) {
                if ( !($assoc['cascade'] & ClassMetadata::CASCADE_PERSIST) ) {
                    throw new \InvalidArgumentException("A new entity was found through a relationship that was not"
                            . " configured to cascade persist operations: " . self::objToStr($entry) . "."
                            . " Explicitly persist the new entity or configure cascading persist operations"
                            . " on the relationship.");
                }
                $this->persistNew($targetClass, $entry);
                $this->computeChangeSet($targetClass, $entry);
            } else if ($state == self::STATE_REMOVED) {
                return new \InvalidArgumentException("Removed entity detected during flush: "
                        . self::objToStr($entry).". Remove deleted entities from associations.");
            } else if ($state == self::STATE_DETACHED) {
                // Can actually not happen right now as we assume STATE_NEW,
                // so the exception will be raised from the DBAL layer (constraint violation).
                throw new \InvalidArgumentException("A detached entity was found through a "
                        . "relationship during cascading a persist operation.");
            }
            // MANAGED associated entities are already taken into account
            // during changeset calculation anyway, since they are in the identity map.
        }
    }

    /**
     * Persist new document, marking it managed and generating the id.
     *
     * This method is either called through `DocumentManager#persist()` or during `DocumentManager#flush()`,
     * when persistence by reachability is applied.
     *
     * @param ClassMetadata $class
     * @param object $document
     * @return void
     */
    public function persistNew($class, $document)
    {
        $id = $this->getIdGenerator($class->idGenerator)->generate($document, $class, $this->dm);

        $this->registerManaged($document, $id, null);

        if ($this->evm->hasListeners(Event::prePersist)) {
            $this->evm->dispatchEvent(Event::prePersist, new Events\LifecycleEventArgs($document, $this->dm));
        }
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

            $data = $this->metadataResolver->createDefaultDocumentStruct($class);
            
            // Convert field values to json values.
            foreach ($this->originalData[$oid] AS $fieldName => $fieldValue) {
                if (isset($class->fieldMappings[$fieldName])) {
                    if ($fieldValue !== null && isset($class->fieldMappings[$fieldName]['embedded'])) {
                        // As we store the serialized value in originalEmbeddedData, we can simply copy here.
                        $fieldValue = $this->originalEmbeddedData[$oid][$class->fieldMappings[$fieldName]['jsonName']];

                    } else if ($fieldValue !== null) {
                        $fieldValue = Type::getType($class->fieldMappings[$fieldName]['type'])
                            ->convertToCouchDBValue($fieldValue);
                    }

                    $data[$class->fieldMappings[$fieldName]['jsonName']] = $fieldValue;

                } else if (isset($class->associationsMappings[$fieldName])) {
                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if (\is_object($fieldValue)) {
                            $fieldValue = $this->getDocumentIdentifier($fieldValue);
                        } else {
                            $fieldValue = null;
                        }
                        $data = $this->metadataResolver->storeAssociationField($data, $class, $this->dm, $fieldName, $fieldValue);
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // TODO: Optimize when not initialized yet! In ManyToMany case we can keep track of ALL ids
                            $ids = array();
                            if (is_array($fieldValue) || $fieldValue instanceof \Doctrine\Common\Collections\Collection) {
                                foreach ($fieldValue AS $relatedObject) {
                                    $ids[] = $this->getDocumentIdentifier($relatedObject);
                                }
                            }
                            $data = $this->metadataResolver->storeAssociationField($data, $class, $this->dm, $fieldName, $ids);
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

    private static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj).'@'.spl_object_hash($obj);
    }
}
