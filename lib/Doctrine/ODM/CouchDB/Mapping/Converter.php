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

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\CouchDB\UnitOfWork;
use Doctrine\ODM\CouchDB\Proxy\Proxy;
use Doctrine\ODM\CouchDB\Types\Type;
use Doctrine\ODM\CouchDB\PersistentCollection;
use Doctrine\ODM\CouchDB\PersistentIdsCollection;
use Doctrine\ODM\CouchDB\PersistentViewCollection;
use Doctrine\ODM\CouchDB\Attachment;
use Doctrine\ODM\CouchDB\Event;
use Doctrine\ODM\CouchDB\Events\LifecycleEventArgs;
use Doctrine\ODM\CouchDB\Events\ConflictEventArgs;

/**
 * Object to array and array to object roundtripping converter. Conversion rules set by
 * ClassMetadata.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Bartfai Tamas <bartfaitamas@gmail.com>
 *
 */
class Converter
{
    private $instance;
    
    private $classMetadata;
    
    private $uow;

    private $validateMetadata = true;
    
    /**
     * There is no need for a differentiation between original and changeset data in CouchDB, since
     * updates have to be complete updates of the document (unless you are using an update handler, which
     * is not yet a feature of CouchDB ODM).
     *
     * @var array
     */
    private $originalData = array();
    
    /**
     * CouchDB always returns and updates the whole data of a document. If on update data is "missing"
     * this means the data is deleted. This also applies to attachments. This is why we need to ensure
     * that data that is not mapped is not lost. This map here saves all the "left-over" data and keeps
     * track of it if necessary.
     *
     * @var array
     */
    private $nonMappedData = array();
    
    private $metadata = array();
    
    protected $childConverters = array();


    private $actualData = array();

    private $actualMetadata = array();

    private $actualChildConverters = array();

    private $visitedCollections = array();
 
    private $state = UnitOfWork::STATE_NEW;

    private $rev;

    /**
     * We store the identifier, because new objects hasn't one, and we can't set
     * the id property until save.
     */
    private $identifier;
    
    /**
     * @var MetadataResolver
     */
    private $metadataResolver;

    public function __construct($instance, $class, /*UnitOfWork*/ $uow)
    {
        $this->instance = $instance;
        $this->uow = $uow;
        $this->metadataResolver = $this->uow->getMetadataResolver();
        
        $this->classMetadata = (is_object($class)) ? $class : $this->uow->getDocumentManager()->getClassMetadata($class);

        if (!$this->classMetadata->isEmbeddedDocument) {
            $this->identifier = $this->classMetadata->getIdentifierValue($this->instance);
            if ($this->classMetadata->isVersioned) {
                $this->rev = $this->classMetadata->getFieldValue($this->instance, $this->classMetadata->versionField);
            }
        }
    }

    public function getRevision()
    {
        return $this->rev;
    }

    public function setRevision($rev)
    {
        if ($this->instance === null) {
            throw new \Exception('Invalid state: setRevision called, instance is null');
        }
        if ($this->classMetadata->isVersioned) {
            $this->classMetadata->setFieldValue($this->instance, $this->classMetadata->versionField, $rev);
        }
        $this->rev = $rev;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }
    
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    // -----------------------------------------------------------------------------
    //  Refresh related methods
    // -----------------------------------------------------------------------------

    private function getTypeFromMetadata(&$data)
    {
        if ($this->metadataResolver->canMapDocument($data)) {
            return $this->metadataResolver->getDocumentType($data);
        } else {
            throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }
    }

    private function handleConflicts(&$data, $documentName) {
        if (isset($data['_conflicts'])) {
            $conflictEvent = new ConflictEventArgs($data, 
                                                          $this->uow->getDocumentManager(), 
                                                          $documentName);
            $this->getEventManager()->dispatchEvent(Event::onConflict, $conflictEvent);
            // TODO Conflict management
            // if ($conflictEvent->conflictResolved()) { $this->uow->getDocumentManager()->find($documentName, $id); }
            // else if (($error = $conflictEvent->getError()) !== null) { throw $error; }
        }
    }

    /**
     * Refreshes the entity with the data loaded from the datasource. 
     * The cascadeContext param is used by UnitOfWork to implement CASCADE_PERSIST
     * association mapping.
     *
     * 
     * @param array
     * @param SerializationContext
     */
    public function refresh(&$data, $cascadeContext = null)
    {
        $this->actualData = array();
        $this->actualMetadata = array();
        $this->actualChildConverters = array();
        $this->visitedCollections = array();

        // before we do anything with our instance, we check for trouble spots
        $this->identifier = (isset($data['_id'])) ? $data['_id'] : null;
        $this->rev = (isset($data['_rev'])) ? $data['_rev'] : null;

        //$documentName = $this->checkDocumentClass($data);
        $documentName = $this->getTypeFromMetadata($data);
        $this->handleConflicts($data, $documentName);

        foreach ($data as $jsonName => $jsonValue) {
            if ($fieldMapping = $this->getFieldMappingForJsonName($jsonName)) {
                $fieldName = $fieldMapping['fieldName'];
                $fieldType = $fieldMapping['type'];

                if (isset($fieldMapping['embedded'])) {
                    $this->refreshEmbedded($fieldMapping, $fieldName, $jsonValue);
                } else {
                    // simple value mapping
                    $this->originalData[$jsonName] = $jsonValue;

                    $phpValue = ($jsonValue === null) 
                        ? null 
                        : Type::getType($fieldType)->convertToPHPValue($jsonValue);
                    $this->setFieldInInstance($fieldName, $phpValue);
                }
            } else if ($jsonName == 'doctrine_metadata') {
                // handle metadata
                $this->innerSetMetadata($jsonName, $jsonValue);
                if (isset($jsonValue['associations'])) {
                    $this->refreshAssociations($jsonName, $jsonValue, $cascadeContext);
                }
            } else if ($jsonName == '_rev' || $jsonName == '_conflicts') {
                continue;
            } else if ($this->classMetadata->hasAttachments && $jsonName == '_attachments') {
                $this->refreshAttachment($data['_id'], $jsonValue);
            } else {
                $this->nonMappedData[$jsonName] = $jsonValue;
            }
        }

        // initialize inverse side collections
        foreach($this->classMetadata->associationsMappings AS $assocName => $assocOptions) {
            if (!$assocOptions['isOwning'] && $assocOptions['type'] & ClassMetadata::TO_MANY) {
                $assocValue = new PersistentViewCollection(
                    new ArrayCollection(),
                    $this->uow->getDocumentManager(),
                    $data['_id'],
                    $this->classMetadata->associationsMappings[$assocName]['mappedBy']);
                $this->setFieldInInstance($assocName, $assocValue);
            }
        }

        if ($this->getEventManager()->hasListeners(Event::postLoad)) {
            $this->getEventManager()->dispatchEvent(
                Event::postLoad, 
                new LifecycleEventArgs($this->instance, $this->uow->getDocumentManager()));
        }
    }

    private function refreshAttachment($id, &$jsonValue)
    {
        $this->innerSetMetadata('_attachments', $jsonValue);

        $attachments = array();
        $dm = $this->uow->getDocumentManager();
        $client = $dm->getConfiguration()->getHttpClient();
        $basePath = '/' . $dm->getConfiguration()->getDatabase() . '/' . $id . '/';
        foreach ($jsonValue AS $filename => $attachment) {
            if (isset($attachment['stub']) && $attachment['stub']) {
                $instance = Attachment::createStub($attachment['content_type'], $attachment['length'], $attachment['revpos'], $client, $basePath . $filename);
            } else if (isset($attachment['data'])) {
                $instance = Attachment::createFromBase64Data($attachment['data'], $attachment['content_type'], $attachment['revpos']);
            }

            $attachments[$filename] = $instance;
        }
        $this->setFieldInInstance($this->classMetadata->attachmentField, $attachments);
    }

    private function refreshAssociations($jsonName, $jsonValue, $cascadeContext = null)
    {
        $resolvedAssociations = array();
        $resolvedAssociations = $this->metadataResolver->resolveJsonField(
            $this->classMetadata,
            $this->uow->getDocumentManager(),
            $resolvedAssociations,
            $jsonName,
            $jsonValue
            );
        foreach ($resolvedAssociations as $fieldName => $resolvedAssociation) {
            $this->setFieldInInstance($fieldName, $resolvedAssociation);
        }

        if ($cascadeContext !== null) {
            $cascades = array();
            foreach ($jsonValue['associations'] as $assocName => $assocValue) {
                if (isset($class->associationsMappings[$assocName])) {
                    $associationsMapping = $class->associationsMappings[$assocName];
                    if ($associationsMapping['type'] & ClassMetadata::TO_ONE) {
                        if ($assocValue) {
                            if ($associationsMapping['cascade'] & ClassMetadata::CASCADE_REFRESH) {
                                $cascades[] = $assocValue;
                            }
                        }
                    } else if ($associationsMapping['type'] & ClassMetadata::TO_MANY) {
                        if ($assocValue && $associationsMapping['isOwning']) {
                            if ($associationsMapping['cascade'] & ClassMetadata::CASCADE_REFRESH) {
                                $cascades = array_merge($cascades, $assocValue);
                            }
                        }
                    }
                }
            }
            
            foreach ($cascades as $id) {
                $cascadeContext->cascadeRefresh($id);
            }
        }
    }

    private function refreshEmbedded(&$fieldMapping, $fieldName, &$jsonValue)
    {
        if ($fieldMapping['embedded'] === 'one') {
            $embeddedConverters = ($jsonValue == null) ? null : $this->createChildConverter($jsonValue);
            $instances = ($jsonValue == null) ? null : $embeddedConverters->instance;

        } else if ($fieldMapping['embedded'] === 'many') {
            $instances = array();
            $embeddedConverters = array();
            if ($jsonValue !== null) {
                foreach ($jsonValue as $jsonKey => $jsonItem) {
                    $converter = $this->createChildConverter($jsonItem);
                    $embeddedConverters[$jsonKey] = $converter;
                    $instances[$jsonKey] = $converter->instance;
                }
            }
        }
        $this->setFieldInInstance($fieldName, $instances);
        $this->childConverters[$fieldMapping['jsonName']] = $embeddedConverters;
    }

    private function createChildConverter($jsonValue)
    {
        $embeddedClassName = $this->getTypeFromMetadata($jsonValue);
        $embeddedClass = $this->uow->getDocumentManager()->getClassMetadata($embeddedClassName);
        $embeddedInstance = $embeddedClass->newInstance();
        $converter = new Converter($embeddedInstance, $embeddedClassName, $this->uow);
        $converter->refresh($jsonValue);
        return $converter;
    }

    private function setFieldInInstance($fieldName, $value)
    {
        if (isset($this->classMetadata->reflFields[$fieldName])) {
            $field = $this->classMetadata->reflFields[$fieldName];
            $field->setValue($this->instance, $value);
        }
    }
    
    private function innerSetMetadata($key, $value) 
    {
        $this->metadata[$key] = $value;
    }

    private function getFieldMappingForJsonName($jsonName)
    {
        if (isset($this->classMetadata->jsonNames[$jsonName]) 
            && isset($this->classMetadata->fieldMappings[$this->classMetadata->jsonNames[$jsonName]])) {

            return $this->classMetadata->fieldMappings[$this->classMetadata->jsonNames[$jsonName]];
        }
        return false;
    }

    // -----------------------------------------------------------------------------
    //  End of refresh related methods.
    // -----------------------------------------------------------------------------

    // -----------------------------------------------------------------------------
    //  Generating actual state
    // -----------------------------------------------------------------------------
    /**
     * Updates the actual state of the entity. Other methods like isChanged or serialize
     * use the actual state, so this method should be called before serializing the object.
     */
    public function updateActualState($context)
    {
        $this->actualData = array();
        $this->actualMetadata = $this->metadataResolver->createDefaultDocumentStruct($this->classMetadata);
        $this->actualChildConverters = array();

        $actualData = array();
        $class = $this->classMetadata;
        foreach ($class->reflFields as $fieldName => $reflProp) {
            $propValue = $reflProp->getValue($this->instance);

            if (isset($class->fieldMappings[$fieldName])) {
                $fieldMapping = $class->fieldMappings[$fieldName];
                if (isset($fieldMapping['embedded'])) {
                    $this->updateActualEmbedded($fieldMapping, $propValue, $context);
                } else {
                    $this->updateActualSimple($fieldMapping, $propValue);
                }
            } else if (isset($class->associationsMappings[$fieldName])) {
                $this->checkAssociationStates($fieldName, $propValue, $context);
                $this->updateActualAssociation($fieldName, $propValue);
            } else if ($class->hasAttachments && $fieldName == $class->attachmentField) {
                $this->updateActualAttachments($propValue);
            }
        }
    }

    private function updateActualEmbedded($fieldMapping, $propValue, $context)
    {
        if ($propValue == null) {
            $childConverter = null;
        } else {
            $originalChildConverter = (isset($this->childConverters[$fieldMapping['jsonName']])) ?
                $this->childConverters[$fieldMapping['jsonName']] :
                null;
            
            if ('one' === $fieldMapping['embedded']) {
                // New converter for a new instance. This new converter will be in changed state
                // since we don't call refresh().
                if ($originalChildConverter === null || $propValue !== $originalChildConverter->getInstance()) {
                    $childConverter = new Converter($propValue, get_class($propValue), $this->uow);
                } else {
                    $childConverter = $originalChildConverter;
                }
                $childConverter->updateActualState($context);
            } else if ('many' === $fieldMapping['embedded']) {
                $childConverter = array();
                foreach ($propValue as $key => $value) {
                    if ($originalChildConverter === null 
                        || !isset($originalChildConverter[$key])
                        || $value !== $originalChildConverter[$key]->getInstance()) {
                        $childConverter[$key] = new Converter($value, get_class($value), $this->uow);
                    } else {
                        $childConverter[$key] = $originalChildConverter[$key];
                    }
                    $childConverter[$key]->updateActualState($context);
                }
            }
        }

        if (!empty($childConverter) || isset($this->childConverters[$fieldMapping['jsonName']])) {
            $this->actualChildConverters[$fieldMapping['jsonName']] = $childConverter;
        }
    }

    private function updateActualSimple($fieldMapping, $propValue)
    {
        if ($propValue == null) {
            // If there is no such field in original data, we skip it to not change the
            // original if there is no change otherwise
            if (isset($this->originalData[$fieldMapping['jsonName']])) {
                $this->originalData[$fieldMapping['jsonName']] = null;
            }
        } else {
            $this->actualData[$fieldMapping['jsonName']] = 
                Type::getType($fieldMapping['type'])->convertToCouchDBValue($propValue);

        }

    }

    private function updateActualAttachments($propValue)
    {
        if (is_array($propValue) && $propValue) {
            $attachments = array();
            foreach ($propValue as $filename => $attachment) {                
                if (!($attachment instanceof \Doctrine\ODM\CouchDB\Attachment)) {
                    throw CouchDBException::invalidAttachment($class->name, $this->documentIdentifiers[$oid], $filename);
                }
                $attachments[$filename] = $attachment->toArray();
            }
            $this->actualMetadata['_attachments'] = $attachments;
        }
    }

    private function updateActualAssociation($fieldName, $propValue)
    {
        $assocMapping = $this->classMetadata->associationsMappings[$fieldName];
        if (!$assocMapping['isOwning']) {
            return;
        }

        $ids = null;
        if ($assocMapping['type'] & ClassMetadata::TO_ONE && \is_object($propValue)) {
            $ids = $this->uow->getDocumentIdentifier($propValue);
        } else if ($assocMapping['type'] & ClassMetadata::TO_MANY) {
            // Optimize when not initialized yet! In ManyToMany case we can keep track of ALL ids
            // If the PC isn't changed, we copy the originally loaded assocations array
            if ($propValue instanceof PersistentCollection && !$propValue->changed()) {
                $ids = $this->metadata['doctrine_metadata']['associations'][$fieldName];
            
            } else if (is_array($propValue) || $propValue instanceof \Doctrine\Common\Collections\Collection) {
                $ids = array();
                foreach($propValue as $relatedObject) {
                    $ids[] = $this->uow->getDocumentIdentifier($relatedObject);
                }
            }
            if ($propValue instanceof \Doctrine\ODM\CouchDB\PersistentCollection && $propValue->changed()) {
                $this->visitedCollections[] = $propValue;
            }
        }
        if ($ids !== null) {
            $this->actualMetadata = $this->metadataResolver->storeAssociationField(
                $this->actualMetadata,
                $this->classMetadata,
                $this->uow->getDocumentManager(),
                $fieldName,
                $ids
            );
        }
    }

    private function checkAssociationStates($fieldName, $value, $serializationContext)
    {
        if ($value == null) {
            return ;
        }
        $assocMapping = $this->classMetadata->associationsMappings[$fieldName];
        if (!$assocMapping['isOwning']) {
            return;
        }

        if ($assocMapping['type'] & ClassMetadata::TO_ONE) {
            if ($value instanceof Proxy && !$value->__isInitialized__) {
                return; // Ignore uninitialized proxy objects
            }
            $value = array($value);
        } else if ($value instanceof PersistentCollection) {
            $value = $value->unwrap(); // Uninitialized collections will simply be empty
        }
        $targetClass = $this->uow->getDocumentManager()->getClassMetadata($assocMapping['targetDocument']);
        foreach ($value as $entry) {
            $state = $this->uow->getDocumentState($entry);
            if ($state == UnitOfWork::STATE_NEW) {
                if (!($assocMapping['cascade'] & ClassMetadata::CASCADE_PERSIST)) {
                    throw new \InvalidArgumentException("A new entity was found through a relationship that was not"
                            . " configured to cascade persist operations: " . UnitOfWork::objToStr($entry) . "."
                            . " Explicitly persist the new entity or configure cascading persist operations"
                            . " on the relationship.");
                }

                // Notify the serializationContext about the new entity,
                // then write the newly assigned id into our metadata structure
                $newEntryId = $serializationContext->cascadePersistNew($targetClass, $entry);
            } else if ($state == UnitOfWork::STATE_REMOVED) {
                return new \InvalidArgumentException("Removed entity detected during flush: "
                        . UnitOfWork::objToStr($entry).". Remove deleted entities from associations.");
            } else if ($state == UnitOfWork::STATE_DETACHED) {
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
     * Serializes the entity into an array. This array can be encoded straight into
     * json to send to couchdb. If the entity is not changed, the returned array is
     * the same as the array passed to refresh().
     *
     * @return array
     */
    public function serialize()
    {
        $result = (empty($this->nonMappedData)) ? 
            $this->actualData :
            array_merge($this->nonMappedData, $this->actualData);

        foreach ($this->actualChildConverters as $fieldName => $childConverter) {
            if ($childConverter === null) {
                $result[$fieldName] = null;
            } else if (is_array($childConverter)) {
                $result[$fieldName] = array();
                foreach ($childConverter as $key => $converter) {
                    $result[$fieldName][$key] = $converter->serialize();
                }
            } else {
                $result[$fieldName] = ($childConverter === null) ? null : $childConverter->serialize();
            }
        }

        $result = array_merge($result, $this->actualMetadata);
        return $result;
    }

    /**
     * This method should be called after flush in UOW to update the inner state
     * of this converter.
     */
    public function afterFlush()
    {
        $this->visitedCollections = array();
        $this->originalData = $this->actualData;
        $this->metadata = $this->actualMetadata;
        $this->childConverters = $this->actualChildConverters;

        // replace arrays with persistent collections
        foreach ($this->classMetadata->reflFields as $fieldName => $reflProperty) {
            $value = $reflProperty->getValue($this->instance);
            if ($this->classMetadata->isCollectionValuedAssociation($fieldName) 
                && $value !== null
                && !($value instanceof PersistentCollection)) {
                
                if (!$value instanceof Collection) {
                    $value = new ArrayCollection($value);
                }
                if ($this->classMetadata->associationsMappings[$fieldName]['isOwning']) {
                    $coll = new PersistentIdsCollection(
                        $value,
                        $this->classMetadata->associationsMappings[$fieldName]['targetDocument'],
                        $this->uow->getDocumentManager(),
                        array()
                        );
                } else {
                    $coll = new PersistentViewCollection(
                        $value,
                        $this->uow->getDocumentManager(),
                        $this->getIdentifier(),
                        $this->classMetadata->associationsMappings[$fieldName]['mappedBy']
                        );
                }

                $reflProperty->setValue($this->instance, $coll);
            }
        }
        
        foreach ($this->visitedCollections as $col) {
            $col->takeSnapshot();
        }

        foreach ($this->childConverters as $childConverter) {
            if ($childConverter === null) {
                $childConverter = array();
            } else if (!is_array($childConverter)) {
                $childConverter = array($childConverter);
            }

            foreach ($childConverter as $converter) {
                $converter->afterFlush();
            }
        }

    }

    /**
     * Tells if the entity changed after the last refresh().
     */
    public function isChanged()
    {
        if ($this->instance instanceof Proxy && !$this->instance->__isInitialized__) {
            return false;
        }

        // Assuming that originalData always contains '_id' when loaded from the database
        // otherwise this is a new document, thus always changed.
        if (empty($this->originalData) ||
            $this->originalData != $this->actualData ||
            $this->metadata != $this->actualMetadata) {
            return true;
        }
        
        if (count($this->childConverters) != count($this->actualChildConverters)) {
            return true;
        }
        foreach ($this->childConverters as $key => $childConverters) {
            if (!isset($this->actualChildConverters[$key])) {
                return true;
            }
            if (!is_array($childConverters)) {
                $childConverters = array($childConverters);
            }

            $actualConverters = $this->actualChildConverters[$key];
            if (!is_array($actualConverters)) { 
                $actualConverters = array($actualConverters); 
            }
            if (count($childConverters) != count($actualConverters)) {
                return true;
            }

            foreach ($childConverters as $key => $childConverter) {
                if (!isset($actualConverters[$key]) || $actualConverters[$key]->isChanged()) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function getEventManager()
    {
        return $this->uow->getDocumentManager()->getEventManager();
    }

    public function getInstance()
    {
        return $this->instance;
    }
    
    public function setInstance($instance)
    {
        $this->instance = $instance;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }
    
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }
    
    public function getNonMappedData()
    {
        return $this->nonMappedData;
    }
    
    public function setNonMappedData($nonMappedData)
    {
        $this->nonMappedData = $nonMappedData;
    }
    
    public function getOriginalData()
    {
        return $this->originalData;
    }
    
    public function setOriginalData($originalData)
    {
        $this->originalData = $originalData;
    }

    public function getChildConverters()
    {
        return $this->childConverters;
    }

    public function getClassMetadata()
    {
        return $this->classMetadata;
    }
    
    public function getActualChildConverters()
    {
        return $this->actualChildConverters;
    }
    
    public function setActualChildConverters($actualChildConverters)
    {
        $this->actualChildConverters = $actualChildConverters;
    }
    
    public function getActualMetadata()
    {
        return $this->actualMetadata;
    }
    
    public function setActualMetadata($actualMetadata)
    {
        $this->actualMetadata = $actualMetadata;
    }
    
    public function getActualData()
    {
        return $this->actualData;
    }
    
    public function setActualData($actualData)
    {
        $this->actualData = $actualData;
    }

    public function getState()
    {
        return $this->state;
    }
    
    public function setState($state)
    {
        $this->state = $state;
    }

    public function isInState($state)
    {
        return $this->state === $state;
    }

    public function getValidateMetadata()
    {
        return $this->validateMetadata;
    }
    
    public function setValidateMetadata($validateMetadata)
    {
        $this->validateMetadata = $validateMetadata;
    }
    

}

