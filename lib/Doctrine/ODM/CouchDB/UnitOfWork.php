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

use Doctrine\ODM\CouchDB\Mapping\Converter;
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
    }

    public function createDocument($documentName, $data, array &$hints = array())
    {
        if (!$this->metadataResolver->canMapDocument($data)) {
            throw new \InvalidArgumentException("Missing or missmatching metadata description in the Document, cannot hydrate!");
        }

        $id = $data['_id'];
        $instance = null;
        if ( ($converter = $this->byId($id)) !== null) {
            $instance = $converter->getInstance();
            if (($instance instanceof Proxy && !$instance->__isInitialized__) || 
                isset($hints['refresh'])) {
                $converter->refresh($data);
            }
            if ($this->dm->getConfiguration()->getValidateDoctrineMetadata()) {
            }
        } else {
            $type = $this->metadataResolver->getDocumentType($data);
            $class = $this->dm->getClassMetadata($type);

            $converter = new Converter($class->newInstance(), $class, $this);

            if ($documentName && !($converter->getInstance() instanceof $documentName)) {
                throw new InvalidDocumentTypeException($type, $documentName);
            }

            $converter->setValidateMetadata(isset($documentName) && $this->dm->getConfiguration()->getValidateDoctrineMetadata());
            $converter->refresh($data);
            $converter->setState(self::STATE_MANAGED);
            $this->identityMap[$id] = $converter;

            $instance = $converter->getInstance();
            $this->documentIdentifiers[\spl_object_hash($instance)] = $id;
        }
        $instance = $converter->getInstance();
        return $instance;
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

    public function registerManaged($document, $identifier, $revision = null)
    {
        $class = $this->dm->getClassMetadata(\get_class($document));

        $converter = new Converter($document, $class, $this);
        $converter->setIdentifier($identifier);
        $converter->setValidateMetadata($this->dm->getConfiguration()->getValidateDoctrineMetadata());
        $converter->setState(self::STATE_MANAGED);

        $this->identityMap[$identifier] = $converter;
        $this->documentIdentifiers[\spl_object_hash($document)] = $identifier;
    }

    public function getDocumentState($document)
    {
        $oid = \spl_object_hash($document);
        if ( ($converter = $this->byOid($oid)) !== null) {
            return $converter->getState();
        }
        return self::STATE_NEW;
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
                $this->byOid($oid)->setState(self::STATE_MANAGED);
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
        $converter = $this->byOid($oid);
        if ($converter === null) {
            throw new \Exception('EHH');
        }

        $visited[$oid] = true;
        
        $converter->setState(self::STATE_REMOVED);

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


    private function bulkUpdate($bulkUpdater, $converter, $context)
    {
        if ($converter->isInState(self::STATE_REMOVED)) {
            $bulkUpdater->deleteDocument($converter->getIdentifier(), $converter->getRevision());
            $this->removeFromIdentityMap($converter->getInstance());
            
            if ($this->evm->hasListeners(Event::postRemove)) {
                $this->evm->dispatchEvent(Event::postRemove, new Events\LifecycleEventArgs($converter->getInstance(), $this->dm));
            }
        } else if ($converter->isInState(self::STATE_NEW) || $converter->isInState(self::STATE_MANAGED)) {
            $converter->updateActualState($context);
            if (!$converter->isChanged()) {
                return;
            }

            if ($this->evm->hasListeners(Event::preUpdate)) {
                $this->evm->dispatchEvent(Event::preUpdate, 
                                          new Events\LifecycleEventArgs($converter->getInstance(), 
                                                                        $this->dm));
            }
            
            $data = $converter->serialize();
            $rev = $converter->getRevision();
            if ($rev) {
                $data['_rev'] = $rev;
            } else {
                unset($data['_rev']);
            }
            $bulkUpdater->updateDocument($data);
        }
    }

    /**
     * Flush Operation - Write all dirty entries to the CouchDB.
     *
     * @return void
     */
    public function flush()
    {
        if ($this->evm->hasListeners(Event::onFlush)) {
            $this->evm->dispatchEvent(Event::onFlush, new Events\OnFlushEventArgs($this));
        }

        $config = $this->dm->getConfiguration();

        $bulkUpdater = $this->dm->getCouchDBClient()->createBulkUpdater();
        $bulkUpdater->setAllOrNothing($config->getAllOrNothingFlush());

        $context = new SerializationContext($this);
        foreach ($this->identityMap as $converter) {
            $this->bulkUpdate($bulkUpdater, $converter, $context);
        }

        while(!empty($context->cascadeNew)) {
            $ids = $context->cascadeNew;
            $context->cascadeNew = array();
            foreach ($ids as $scheduleId) {
                $this->bulkUpdate($bulkUpdater, $this->byOid($scheduleId), $context);
            }
        }
        
        $response = $bulkUpdater->execute();
        $updateConflictDocuments = array();
        if ($response->status == 201) {
            foreach ($response->body AS $docResponse) {
                if (!isset($this->identityMap[$docResponse['id']])) {
                    // deletions
                    continue;
                }

                $converter = $this->identityMap[$docResponse['id']];
                $document = $converter->getInstance();

                if (isset($docResponse['error'])) {
                    $updateConflictDocuments[] = $document;
                } else {
                    $converter->setRevision($docResponse['rev']);
                }

                if ($this->evm->hasListeners(Event::postUpdate)) {
                    $this->evm->dispatchEvent(Event::postUpdate, new Events\LifecycleEventArgs($document, $this->dm));
                }
            }
        }
        foreach ($this->identityMap as $converter) {
            $converter->afterFlush();
        }

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
                  $this->documentIdentifiers[$oid]
                );

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
            return $this->identityMap[$id]->getInstance();
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
        if (isset($this->documentIdentifiers[$oid])) {
            return $this->identityMap[$this->documentIdentifiers[$oid]]->getRevision();
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

    private function byOid($oid) {
        if (isset($this->documentIdentifiers[$oid])) {
            return $this->identityMap[$this->documentIdentifiers[$oid]];
        }
        return null;
    }
    
    private function byId($id)
    {
        return (isset($this->identityMap[$id])) ? $this->identityMap[$id] : null;
    }

    public function getDocumentManager()
    {
        return $this->dm;
    }

    public function getMetadataResolver()
    {
        return $this->metadataResolver;
    }

    public static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj).'@'.spl_object_hash($obj);
    }

}

class SerializationContext
{
    private $uow;

    public $cascadeNew = array();

    public function __construct(UnitOfWork $uow) 
    {
        $this->uow = $uow;
    }

    public function cascadePersistNew($class, $document)
    {
        $this->uow->persistNew($class, $document);
        $this->cascadeNew[] = \spl_object_hash($document);
        return $this->uow->getDocumentIdentifier($document);
    }
}
