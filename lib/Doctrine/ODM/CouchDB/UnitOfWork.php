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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\CouchDB\Attachment;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Types\Type;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\CouchDB\HTTP\HTTPException;

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
     * @var \Doctrine\Common\EventManager
     */
    private $evm;

    /**
     * @var \Doctrine\ODM\CouchDB\Mapping\MetadataResolver\MetadataResolver
     */
    private $metadataResolver;

    /**
     * @var \Doctrine\ODM\CouchDB\Migrations\DocumentMigration
     */
    private $migrations;

    /**
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        $this->evm = $dm->getEventManager();
        $this->metadataResolver = $dm->getConfiguration()->getMetadataResolverImpl();
        $this->migrations = $dm->getConfiguration()->getMigrations();

        $this->embeddedSerializer = new Mapping\EmbeddedDocumentSerializer($this->dm->getMetadataFactory(),
                                                                           $this->metadataResolver);
    }

    private function assertValidDocumentType($documentName, $document, $type)
    {
        if ($documentName && !($document instanceof $documentName)) {
            throw new InvalidDocumentTypeException($type, $documentName);
        }
    }

    /**
     * Create a document given class, data and the doc-id and revision
     *
     * @param string $documentName
     * @param array  $data
     * @param array  $hints
     *
     * @return object
     * @throws \InvalidArgumentException
     * @throws InvalidDocumentTypeException
     */
    public function createDocument($documentName, $data, array &$hints = array())
    {
        $data = $this->migrations->migrate($data);

        if (!$this->metadataResolver->canMapDocument($data)) {
            throw new \InvalidArgumentException("Missing or mismatching metadata description in the Document, cannot hydrate!");
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
            } else if ($jsonName == '_rev' || $jsonName == "type") {
                continue;
            } else if ($jsonName == '_conflicts') {
                $conflict = true;
            } else if ($class->hasAttachments && $jsonName == '_attachments') {
                $documentState[$class->attachmentField] = $this->createDocumentAttachments($id, $jsonValue);
            } else if ($this->metadataResolver->canResolveJsonField($jsonName)) {
                $documentState = $this->metadataResolver->resolveJsonField($class, $this->dm, $documentState, $jsonName, $data);
            } else {
                $nonMappedData[$jsonName] = $jsonValue;
            }
        }

        if ($conflict && $this->evm->hasListeners(Event::onConflict)) {
            // there is a conflict and we have an event handler that might resolve it
            $this->evm->dispatchEvent(Event::onConflict, new Event\ConflictEventArgs($data, $this->dm, $type));
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
                    $class->associationsMappings[$assocName]
                );
            }
        }

        if (isset($this->identityMap[$id])) {
            $document = $this->identityMap[$id];
            $overrideLocalValues = false;

            $this->assertValidDocumentType($documentName, $document, $type);

            if ( ($document instanceof Proxy && !$document->__isInitialized__) || isset($hints['refresh'])) {
                $overrideLocalValues = true;
                $oid = spl_object_hash($document);
                $this->documentRevisions[$oid] = $rev;
            }
        } else {
            $document = $class->newInstance();

            $this->assertValidDocumentType($documentName, $document, $type);

            $this->identityMap[$id] = $document;

            $oid = spl_object_hash($document);
            $this->documentState[$oid] = self::STATE_MANAGED;
            $this->documentIdentifiers[$oid] = (string)$id;
            $this->documentRevisions[$oid] = $rev;
            $overrideLocalValues = true;
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
            $this->evm->dispatchEvent(Event::postLoad, new Event\LifecycleEventArgs($document, $this->dm));
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

        $client = $this->dm->getHttpClient();
        $basePath = '/' . $this->dm->getCouchDBClient()->getDatabase() . '/' . $documentId . '/';
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
                throw new \InvalidArgumentException("Detached document passed to persist().");
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
            $this->evm->dispatchEvent(Event::preRemove, new Event\LifecycleEventArgs($document, $this->dm));
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
            $this->removeFromIdentityMap($document);
            throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException();
        }

        $hints = array('refresh' => true);
        $this->createDocument($this->dm->getClassMetadata(get_class($document))->name, $response->body, $hints);

        $this->cascadeRefresh($document, $visited);
    }

    public function merge($document)
    {
        $visited = array();
        return $this->doMerge($document, $visited);
    }

    private function doMerge($document, array &$visited, $prevManagedCopy = null, $assoc = null)
    {
        if (!is_object($document)) {
            throw CouchDBException::unexpectedDocumentType($document);
        }

        $oid = spl_object_hash($document);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $document; // mark visited

        $class = $this->dm->getClassMetadata(get_class($document));

        // First we assume DETACHED, although it can still be NEW but we can avoid
        // an extra db-roundtrip this way. If it is not MANAGED but has an identity,
        // we need to fetch it from the db anyway in order to merge.
        // MANAGED entities are ignored by the merge operation.
        if ($this->getDocumentState($document) == self::STATE_MANAGED) {
            $managedCopy = $document;
        } else {
            $id = $class->getIdentifierValue($document);

            if (!$id) {
                // document is new
                // TODO: prePersist will be fired on the empty object?!
                $managedCopy = $class->newInstance();
                $this->persistNew($class, $managedCopy);
            } else {
                $managedCopy = $this->tryGetById($id);
                if ($managedCopy) {
                    // We have the document in-memory already, just make sure its not removed.
                    if ($this->getDocumentState($managedCopy) == self::STATE_REMOVED) {
                        throw new \InvalidArgumentException('Removed document detected during merge.'
                                . ' Can not merge with a removed document.');
                    }
                } else {
                    // We need to fetch the managed copy in order to merge.
                    $managedCopy = $this->dm->find($class->name, $id);
                }

                if ($managedCopy === null) {
                    // If the identifier is ASSIGNED, it is NEW, otherwise an error
                    // since the managed document was not found.
                    if ($class->idGenerator == ClassMetadata::IDGENERATOR_ASSIGNED) {
                        $managedCopy = $class->newInstance();
                        $class->setIdentifierValue($managedCopy, $id);
                        $this->persistNew($class, $managedCopy);
                    } else {
                        throw new DocumentNotFoundException();
                    }
                }
            }

            if ($class->isVersioned) {
                $managedCopyVersion = $class->reflFields[$class->versionField]->getValue($managedCopy);
                $documentVersion = $class->reflFields[$class->versionField]->getValue($document);
                // Throw exception if versions dont match.
                if ($managedCopyVersion != $documentVersion) {
                    throw OptimisticLockException::lockFailedVersionMissmatch($document, $documentVersion, $managedCopyVersion);
                }
            }

            $managedOid = spl_object_hash($managedCopy);
            // Merge state of $entity into existing (managed) entity
            foreach ($class->reflFields as $name => $prop) {
                if ( ! isset($class->associationsMappings[$name])) {
                    if ( ! $class->isIdentifier($name)) {
                        $prop->setValue($managedCopy, $prop->getValue($document));
                    }
                } else {
                    $assoc2 = $class->associationsMappings[$name];

                    if ($assoc2['type'] & ClassMetadata::TO_ONE) {
                        $other = $prop->getValue($document);
                        if ($other === null) {
                            $prop->setValue($managedCopy, null);
                        } else if ($other instanceof Proxy && !$other->__isInitialized__) {
                            // do not merge fields marked lazy that have not been fetched.
                            continue;
                        } else if ( $assoc2['cascade'] & ClassMetadata::CASCADE_MERGE == 0) {
                            if ($this->getDocumentState($other) == self::STATE_MANAGED) {
                                $prop->setValue($managedCopy, $other);
                            } else {
                                $targetClass = $this->dm->getClassMetadata($assoc2['targetDocument']);
                                $id = $targetClass->getIdentifierValues($other);
                                $proxy = $this->dm->getProxyFactory()->getProxy($assoc2['targetDocument'], $id);
                                $prop->setValue($managedCopy, $proxy);
                                $this->registerManaged($proxy, $id, null);
                            }
                        }
                    } else {
                        $mergeCol = $prop->getValue($document);
                        if ($mergeCol instanceof PersistentCollection && !$mergeCol->isInitialized) {
                            // do not merge fields marked lazy that have not been fetched.
                            // keep the lazy persistent collection of the managed copy.
                            continue;
                        }

                        $managedCol = $prop->getValue($managedCopy);
                        if (!$managedCol) {
                            if ($assoc2['isOwning']) {
                                $managedCol = new PersistentIdsCollection(
                                    new ArrayCollection,
                                    $assoc2['targetDocument'],
                                    $this->dm,
                                    array()
                                );
                            } else {
                                $managedCol = new PersistentViewCollection(
                                    new ArrayCollection,
                                    $this->dm,
                                    $this->documentIdentifiers[$managedOid],
                                    $assoc2
                                );
                            }
                            $prop->setValue($managedCopy, $managedCol);
                            $this->originalData[$managedOid][$name] = $managedCol;
                        }
                        if ($assoc2['cascade'] & ClassMetadata::CASCADE_MERGE > 0) {
                            $managedCol->initialize();
                            if (!$managedCol->isEmpty()) {
                                // clear managed collection, in casacadeMerge() the collection is filled again.
                                $managedCol->unwrap()->clear();
                            }
                        }
                    }
                }
            }
        }

        if ($prevManagedCopy !== null) {
            $assocField = $assoc['fieldName'];
            $prevClass = $this->dm->getClassMetadata(get_class($prevManagedCopy));
            if ($assoc['type'] & ClassMetadata::TO_ONE) {
                $prevClass->reflFields[$assocField]->setValue($prevManagedCopy, $managedCopy);
            } else {
                $prevClass->reflFields[$assocField]->getValue($prevManagedCopy)->add($managedCopy);
                if ($assoc['type'] == ClassMetadata::ONE_TO_MANY) {
                    $class->reflFields[$assoc['mappedBy']]->setValue($managedCopy, $prevManagedCopy);
                }
            }
        }

        // Mark the managed copy visited as well
        $visited[spl_object_hash($managedCopy)] = true;

        $this->cascadeMerge($document, $managedCopy, $visited);

        return $managedCopy;
    }

    /**
     * Cascades a merge operation to associated entities.
     *
     * @param object $document
     * @param object $managedCopy
     * @param array $visited
     */
    private function cascadeMerge($document, $managedCopy, array &$visited)
    {
        $class = $this->dm->getClassMetadata(get_class($document));
        foreach ($class->associationsMappings as $assoc) {
            if ( $assoc['cascade'] & ClassMetadata::CASCADE_MERGE == 0) {
                continue;
            }
            $relatedDocuments = $class->reflFields[$assoc['fieldName']]->getValue($document);
            if ($relatedDocuments instanceof Collection) {
                if ($relatedDocuments instanceof PersistentCollection) {
                    // Unwrap so that foreach() does not initialize
                    $relatedDocuments = $relatedDocuments->unwrap();
                }
                foreach ($relatedDocuments as $relatedDocument) {
                    $this->doMerge($relatedDocument, $visited, $managedCopy, $assoc);
                }
            } else if ($relatedDocuments !== null) {
                $this->doMerge($relatedDocuments, $visited, $managedCopy, $assoc);
            }
        }
    }


    /**
     * Detaches a document from the persistence management. It's persistence will
     * no longer be managed by Doctrine.
     *
     * @param object $document The document to detach.
     */
    public function detach($document)
    {
        $visited = array();
        $this->doDetach($document, $visited);
    }

    /**
     * Executes a detach operation on the given entity.
     *
     * @param object $document
     * @param array $visited
     */
    private function doDetach($document, array &$visited)
    {
        $oid = spl_object_hash($document);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $document; // mark visited

        switch ($this->getDocumentState($document)) {
            case self::STATE_MANAGED:
                if (isset($this->identityMap[$this->documentIdentifiers[$oid]])) {
                    $this->removeFromIdentityMap($document);
                }
                unset($this->scheduledRemovals[$oid], $this->scheduledUpdates[$oid],
                        $this->originalData[$oid], $this->documentRevisions[$oid],
                        $this->documentIdentifiers[$oid], $this->documentState[$oid]);
                break;
            case self::STATE_NEW:
            case self::STATE_DETACHED:
                return;
        }

        $this->cascadeDetach($document, $visited);
    }

    /**
     * Cascades a detach operation to associated documents.
     *
     * @param object $document
     * @param array $visited
     */
    private function cascadeDetach($document, array &$visited)
    {
        $class = $this->dm->getClassMetadata(get_class($document));
        foreach ($class->associationsMappings as $assoc) {
            if ( $assoc['cascade'] & ClassMetadata::CASCADE_DETACH == 0) {
                continue;
            }
            $relatedDocuments = $class->reflFields[$assoc['fieldName']]->getValue($document);
            if ($relatedDocuments instanceof Collection) {
                if ($relatedDocuments instanceof PersistentCollection) {
                    // Unwrap so that foreach() does not initialize
                    $relatedDocuments = $relatedDocuments->unwrap();
                }
                foreach ($relatedDocuments as $relatedDocument) {
                    $this->doDetach($relatedDocument, $visited);
                }
            } else if ($relatedDocuments !== null) {
                $this->doDetach($relatedDocuments, $visited);
            }
        }
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

    /**
     * Get the state of a document.
     *
     * @param  object $document
     * @return int
     */
    public function getDocumentState($document)
    {
        $oid = \spl_object_hash($document);
        if (!isset($this->documentState[$oid])) {
            $class = $this->dm->getClassMetadata(get_class($document));
            $id = $class->getIdentifierValue($document);
            if (!$id) {
                return self::STATE_NEW;
            } else if ($class->idGenerator == ClassMetadata::IDGENERATOR_ASSIGNED) {
                if ($class->isVersioned) {
                    if ($class->getFieldValue($document, $class->versionField)) {
                        return self::STATE_DETACHED;
                    } else {
                        return self::STATE_NEW;
                    }
                } else {
                    if ($this->tryGetById($id)) {
                        return self::STATE_DETACHED;
                    } else {
                        $response = $this->dm->getCouchDBClient()->findDocument($id);

                        if ($response->status == 404) {
                            return self::STATE_NEW;
                        } else {
                            return self::STATE_DETACHED;
                        }
                    }
                }
            } else {
                return self::STATE_DETACHED;
            }
        }
        return $this->documentState[$oid];
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
        if ($document instanceof Proxy && !$document->__isInitialized__) {
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
                        $class->associationsMappings[$fieldName]
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

        // 2. Compare to the original, or find out that this document is new.
        if (!isset($this->originalData[$oid])) {
            // document is New and should be inserted
            $this->originalData[$oid] = $actualData;
            $this->scheduledUpdates[$oid] = $document;
            $this->originalEmbeddedData[$oid] = $embeddedActualData;
        } else {
            // document is "fully" MANAGED: it was already fully persisted before
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
                    if (!isset($this->originalEmbeddedData[$oid][$fieldName])) {
                        $changed = true;
                        break;
                    }

                    $changed = $this->embeddedSerializer->isChanged(
                        $actualData[$fieldName],                        /* actual value */
                        $this->originalEmbeddedData[$oid][$fieldName],  /* original state  */
                        $class->fieldMappings[$fieldName]
                    );

                    if ($changed) {
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
     * @param array $assoc
     * @param mixed $value The value of the association.
     * @return \InvalidArgumentException
     * @throws \InvalidArgumentException
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

        foreach ($value as $entry) {
            $targetClass = $this->dm->getClassMetadata($assoc['targetDocument'] ?: get_class($entry));
            $state = $this->getDocumentState($entry);
            $oid = spl_object_hash($entry);
            if ($state == self::STATE_NEW) {
                if ( !($assoc['cascade'] & ClassMetadata::CASCADE_PERSIST) ) {
                    throw new \InvalidArgumentException("A new document was found through a relationship that was not"
                            . " configured to cascade persist operations: " . self::objToStr($entry) . "."
                            . " Explicitly persist the new document or configure cascading persist operations"
                            . " on the relationship.");
                }
                $this->persistNew($targetClass, $entry);
                $this->computeChangeSet($targetClass, $entry);
            } else if ($state == self::STATE_REMOVED) {
                return new \InvalidArgumentException("Removed document detected during flush: "
                        . self::objToStr($entry).". Remove deleted documents from associations.");
            } else if ($state == self::STATE_DETACHED) {
                // Can actually not happen right now as we assume STATE_NEW,
                // so the exception will be raised from the DBAL layer (constraint violation).
                throw new \InvalidArgumentException("A detached document was found through a "
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
            $this->evm->dispatchEvent(Event::prePersist, new Event\LifecycleEventArgs($document, $this->dm));
        }
    }

    /**
     * Flush Operation - Write all dirty entries to the CouchDB.
     *
     * @throws UpdateConflictException
     * @throws CouchDBException
     * @throws \Doctrine\CouchDB\HTTP\HTTPException
     * @throws DocumentNotFoundException
     *
     * @return void
     */
    public function flush()
    {
        $this->detectChangedDocuments();

        if ($this->evm->hasListeners(Event::onFlush)) {
            $this->evm->dispatchEvent(Event::onFlush, new Event\OnFlushEventArgs($this));
        }

        $config = $this->dm->getConfiguration();

        $bulkUpdater = $this->dm->getCouchDBClient()->createBulkUpdater();
        $bulkUpdater->setAllOrNothing($config->getAllOrNothingFlush());

        foreach ($this->scheduledRemovals AS $oid => $document) {
            if ($document instanceof Proxy && !$document->__isInitialized__) {
                $response = $this->dm->getCouchDBClient()->findDocument($this->getDocumentIdentifier($document));

                if ($response->status == 404) {
                    $this->removeFromIdentityMap($document);
                    throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException();
                }

                $this->documentRevisions[$oid] = $response->body['_rev'];
            }

            $bulkUpdater->deleteDocument($this->documentIdentifiers[$oid], $this->documentRevisions[$oid]);
            $this->removeFromIdentityMap($document);

            if ($this->evm->hasListeners(Event::postRemove)) {
                $this->evm->dispatchEvent(Event::postRemove, new Event\LifecycleEventArgs($document, $this->dm));
            }
        }

        foreach ($this->scheduledUpdates AS $oid => $document) {
            $class = $this->dm->getClassMetadata(get_class($document));

            if ($this->evm->hasListeners(Event::preUpdate)) {
                $this->evm->dispatchEvent(Event::preUpdate, new Event\LifecycleEventArgs($document, $this->dm));
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

                    if (isset($class->fieldMappings[$fieldName]['id'])) {
                        $fieldValue = (string)$fieldValue;
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
                                foreach ($fieldValue AS $key => $relatedObject) {
                                    $ids[$key] = $this->getDocumentIdentifier($relatedObject);
                                }
                            }
                            $data = $this->metadataResolver->storeAssociationField($data, $class, $this->dm, $fieldName, $ids);
                        }
                    }
                } else if ($class->hasAttachments && $fieldName == $class->attachmentField) {
                    if (is_array($fieldValue) && $fieldValue) {
                        $data['_attachments'] = array();
                        foreach ($fieldValue AS $filename => $attachment) {
                            if (!($attachment instanceof \Doctrine\CouchDB\Attachment)) {
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
            $data['_id'] = $this->documentIdentifiers[$oid];

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
                    $this->evm->dispatchEvent(Event::postUpdate, new Event\LifecycleEventArgs($document, $this->dm));
                }
            }
        } else if ($response->status >= 400) {
            throw HTTPException::fromResponse($bulkUpdater->getPath(), $response);
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
        $this->documentIdentifiers[$oid] = (string)$identifier;
        $this->documentRevisions[$oid] = $revision;
        $this->identityMap[$identifier] = $document;
    }

    /**
     * Tries to find an document with the given identifier in the identity map of
     * this UnitOfWork.
     *
     * @param mixed $id The document identifier to look for.
     * @return mixed Returns the document with the specified identifier if it exists in
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
     * Checks whether a document is registered in the identity map of this UnitOfWork.
     *
     * @param object $document
     * @return boolean
     */
    public function isInIdentityMap($document)
    {
        $oid = spl_object_hash($document);
        if ( ! isset($this->documentIdentifiers[$oid])) {
            return false;
        }
        $classMetadata = $this->dm->getClassMetadata(get_class($document));
        if ($this->documentIdentifiers[$oid] === '') {
            return false;
        }

        return isset($this->identityMap[$this->documentIdentifiers[$oid]]);
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

    /**
     * Find many documents by id.
     *
     * Important: Each document is returned with the key it has in the $ids array!
     *
     * @param array $ids
     * @param null|string $documentName
     * @param null|int $limit
     * @param null|int $offset
     * @return array
     * @throws \Exception
     */
    public function findMany(array $ids, $documentName = null, $limit = null, $offset = null)
    {
        $response = $this->dm->getCouchDBClient()->findDocuments($ids, $limit, $offset);
        $keys = array_flip($ids);

        if ($response->status != 200) {
            throw new \Exception("loadMany error code " . $response->status);
        }

        $docs = array();
        foreach ($response->body['rows'] AS $responseData) {
            if (isset($responseData['doc'])) {
                $docs[$keys[$responseData['id']]] = $this->createDocument($documentName, $responseData['doc']);
            }
        }
        return $docs;
    }

    /**
     * Get all entries currently in the identity map
     *
     * @return array
     */
    public function getIdentityMap()
    {
        return $this->identityMap;
    }
}
