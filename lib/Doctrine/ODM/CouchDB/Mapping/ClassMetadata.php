<?php

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Persistence\Mapping\ClassMetadata AS IClassMetadata,
    Doctrine\Instantiator\Instantiator,
    ReflectionClass,
    ReflectionProperty;

/**
 * Metadata class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class ClassMetadata implements IClassMetadata
{
    const IDGENERATOR_UUID = 1;
    const IDGENERATOR_ASSIGNED = 2;

    const TO_ONE = 5;
    const TO_MANY = 10;
    const ONE_TO_ONE = 1;
    const ONE_TO_MANY = 2;
    const MANY_TO_ONE = 4;
    const MANY_TO_MANY = 8;

    const CASCADE_PERSIST = 1;
    const CASCADE_REMOVE  = 2;
    const CASCADE_MERGE   = 4;
    const CASCADE_DETACH  = 8;
    const CASCADE_REFRESH = 16;
    const CASCADE_ALL     = 31;

    public $idGenerator = self::IDGENERATOR_UUID;

    /**
     * READ-ONLY: The field name of the document identifier.
     */
    public $identifier;

    /**
     * READ-ONLY: The name of the document class.
     */
    public $name;

    /**
     * READ-ONLY: The root document class name.
     */
    public $rootDocumentName;

    /**
     * READ-ONLY: Is this entity in an inheritance hierachy?
     */
    public $inInheritanceHierachy = false;

    /**
     * READ-ONLY: a list of all parent classes.
     */
    public $parentClasses = array();

    /**
     * READ-ONLY: The namespace the document class is contained in.
     *
     * @var string
     * @todo Not really needed. Usage could be localized.
     */
    public $namespace;

    /**
     * The name of the custom repository class used for the document class.
     * (Optional).
     *
     * @var string
     */
    public $customRepositoryClassName;

    /**
     * READ-ONLY: The field mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * The mapping definition array has the following values:
     *
     * - <b>fieldName</b> (string)
     * The name of the field in the Document.
     *
     * - <b>id</b> (boolean, optional)
     * Marks the field as the primary key of the document. Multiple fields of an
     * document can have the id attribute, forming a composite key.
     *
     * @var array
     */
    public $fieldMappings = array();

    /**
     * An array of indexed fields, accessible through a generic view shipped with Doctrine.
     *
     * @var array
     */
    public $indexes = array();

    /**
     * Is this class indexed? If yes, then a findAll() query can be executed for this type.
     *
     * @var bool
     */
    public $indexed = false;

    /**
     * An array of json result-key-names to field-names
     *
     * @var array
     */
    public $jsonNames = array();

    /**
     * READ-ONLY: Array of fields to also load with a given method.
     *
     * @var array
     */
    public $alsoLoadMethods = array();

    /**
     * READ-ONLY: Whether this class describes the mapping of a mapped superclass.
     *
     * @var boolean
     */
    public $isMappedSuperclass = false;

    /**
     * READ-ONLY: Whether this class describes the mapping of a embedded document.
     *
     * @var boolean
     */
    public $isEmbeddedDocument = false;

    /**
     * READ-ONLY: Wheather the document or embedded document is read-only and will be skipped in change-tracking.
     *
     * This should be set to true for value objects, for example attachments. Replacing the reference with a new
     * value object will trigger an update.
     *
     * @var bool
     */
    public $isReadOnly = false;

    /**
     * READ-ONLY
     *
     * @var array
     */
    public $associationsMappings = array();

    /**
     * CouchDB documents are always versioned, this flag determines if this version is exposed to the userland.
     *
     * @var bool
     */
    public $isVersioned = false;

    /**
     * Version Field stores the CouchDB Revision
     *
     * @var string
     */
    public $versionField = null;

    /**
     * @var bool
     */
    public $hasAttachments = false;

    /**
     * Field that stores the attachments as a key->value array of file-names to attachment objects.
     *
     * @var string
     */
    public $attachmentField = null;

    /**
     * If in an inheritance scenario the attachment field is on a super class, this is its name.
     *
     * @var string|null
     */
    public $attachmentDeclaredClass = null;

    /**
     * The ReflectionClass instance of the mapped class.
     *
     * @var ReflectionClass
     */
    public $reflClass;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var array
     */
    public $reflFields = array();

    /**
     * @var \Doctrine\Instantiator\InstantiatorInterface
     */
    private $instantiator;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-document mapping
     * metadata of the class with the given name.
     *
     * @param string $documentName The name of the document class the new instance is used for.
     */
    public function __construct($documentName)
    {
        $this->name = $documentName;
        $this->rootDocumentName = $documentName;
        $this->instantiator = new Instantiator();
    }

    /**
     * Used to derive a class metadata of the current instance for a mapped child class.
     *
     * @param  ClassMetadata $child
     * @throws \InvalidArgumentException
     */
    public function deriveChildMetadata($child)
    {
        if (!is_subclass_of($child->name, $this->name)) {
            throw new \InvalidArgumentException("Only child class names of '".$this->name."' are valid values.");
        }

        $child->isMappedSuperclass = false;
        $child->isEmbeddedDocument = false;

        foreach ($this->fieldMappings AS $fieldName => $fieldMapping) {
            $child->fieldMappings[$fieldName] = $fieldMapping;

            if (!isset($fieldMapping['declared'])) {
                $child->fieldMappings[$fieldName]['declared'] = $this->name;
            }
        }

        foreach ($this->associationsMappings AS $assocName => $assocMapping) {
            $child->associationsMappings[$assocName] = $assocMapping;

            if (!isset($assocMapping['declared'])) {
                $child->associationsMappings[$assocName]['declared'] = $this->name;
            }
        }

        if ($this->attachmentField) {
            $child->attachmentField = $this->attachmentField;

            if (!$child->attachmentDeclaredClass) {
                $child->attachmentDeclaredClass = $this->name;
            }
        }
    }

    /**
     * Determines which fields get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     * That means any metadata properties that are not set or empty or simply have
     * their default value are NOT serialized.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     *      - reflClass (ReflectionClass)
     *      - reflFields (ReflectionProperty array)
     *
     * @return array The names of all the fields that should be serialized.
     */
    public function __sleep()
    {
        // This metadata is always serialized/cached.
        $serialized = array(
            'name',
            'associationsMappings',
            'fieldMappings',
            'jsonNames',
            'idGenerator',
            'identifier',
            'rootDocumentName',
        );

        if ($this->inInheritanceHierachy) {
            $serialized[] = 'inInheritanceHierachy';
        }

        if ($this->parentClasses) {
            $serialized[] = 'parentClasses';
        }

        if ($this->isVersioned) {
            $serialized[] = 'isVersioned';
            $serialized[] = 'versionField';
        }

        if ($this->customRepositoryClassName) {
            $serialized[] = 'customRepositoryClassName';
        }

        if ($this->hasAttachments) {
            $serialized[] = 'hasAttachments';
            $serialized[] = 'attachmentField';
            if ($this->attachmentDeclaredClass) {
                $serialized[] = 'attachmentDeclaredClass';
            }
        }

        if ($this->isReadOnly) {
            $serialized[] = 'isReadOnly';
        }

        if ($this->isMappedSuperclass) {
            $serialized[] = 'isMappedSuperclass';
        }

        if ($this->indexed) {
            $serialized[] = 'indexed';
        }
        if ($this->indexes) {
            $serialized[] = 'indexes';
        }

        return $serialized;
    }

    /**
     * Restores some state that can not be serialized/unserialized.
     */
    public function wakeupReflection($reflService)
    {
        // Restore ReflectionClass and properties
        $this->reflClass    = $reflService->getClass($this->name);
        $this->namespace    = $reflService->getClassNamespace($this->name);
        $this->instantiator = $this->instantiator ?: new Instantiator();

        foreach ($this->fieldMappings as $field => $mapping) {
            if (isset($mapping['declared'])) {
                $reflField = new \ReflectionProperty($mapping['declared'], $field);
            } else {
                $reflField = $this->reflClass->getProperty($field);
            }
            $reflField->setAccessible(true);
            $this->reflFields[$field] = $reflField;
        }

        foreach ($this->associationsMappings as $field => $mapping) {
            if (isset($mapping['declared'])) {
                $reflField = new \ReflectionProperty($mapping['declared'], $field);
            } else {
                $reflField = $this->reflClass->getProperty($field);
            }

            $reflField->setAccessible(true);
            $this->reflFields[$field] = $reflField;
        }

        if ($this->hasAttachments) {
            if ($this->attachmentDeclaredClass) {
                $reflField = new \ReflectionProperty($this->attachmentDeclaredClass, $this->attachmentField);
            } else {
                $reflField = $this->reflClass->getProperty($this->attachmentField);
            }
            $reflField->setAccessible(true);
            $this->reflFields[$this->attachmentField] = $reflField;
        }
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @return object
     */
    public function newInstance()
    {
        return $this->instantiator->instantiate($this->name);
    }

    /**
     * Gets the ReflectionClass instance of the mapped class.
     *
     * @return ReflectionClass
     */
    public function getReflectionClass()
    {
        if ( ! $this->reflClass) {
            $this->reflClass = new ReflectionClass($this->name);
        }
        return $this->reflClass;
    }

    /**
     * Gets the ReflectionPropertys of the mapped class.
     *
     * @return array An array of ReflectionProperty instances.
     */
    public function getReflectionProperties()
    {
        return $this->reflFields;
    }

    /**
     * Gets a ReflectionProperty for a specific field of the mapped class.
     *
     * @param string $name
     * @return ReflectionProperty
     */
    public function getReflectionProperty($name)
    {
        return $this->reflFields[$name];
    }


    /**
     * Sets the document identifier of a document.
     *
     * @param object $document
     * @param mixed $id
     */
    public function setIdentifierValue($document, $id)
    {
        $this->reflFields[$this->identifier]->setValue($document, $id);
    }

    /**
     * Gets the document identifier.
     *
     * @param object $document
     * @return string $id
     */
    public function getIdentifierValue($document)
    {
        return (string) $this->reflFields[$this->identifier]->getValue($document);
    }

    /**
     * Get identifier values of this document.
     *
     * Since CouchDB only allows exactly one identifier field this is a proxy
     * to {@see getIdentifierValue()} and returns an array with the identifier
     * field as a key.
     *
     * @param object $document
     * @return array
     */
    public function getIdentifierValues($document)
    {
        return array($this->identifier => $this->getIdentifierValue($document));
    }

    /**
     * Sets the specified field to the specified value on the given document.
     *
     * @param object $document
     * @param string $field
     * @param mixed $value
     */
    public function setFieldValue($document, $field, $value)
    {
        $this->reflFields[$field]->setValue($document, $value);
    }

    /**
     * Gets the specified field's value off the given document.
     *
     * @param object $document
     * @param string $field
     */
    public function getFieldValue($document, $field)
    {
        return $this->reflFields[$field]->getValue($document);
    }

    /**
     * Checks whether a field is part of the identifier/primary key field(s).
     *
     * @param string $fieldName  The field name
     * @return boolean  TRUE if the field is part of the table identifier/primary key field(s),
     *                  FALSE otherwise.
     */
    public function isIdentifier($fieldName)
    {
        return $this->identifier === $fieldName ? true : false;
    }

    /**
     * INTERNAL:
     * Sets the mapped identifier field of this class.
     *
     * @param string $identifier
     * @throws MappingException
     */
    public function setIdentifier($identifier)
    {
        if ($this->isEmbeddedDocument) {
            throw new MappingException('EmbeddedDocument should not have id field');
        }
        $this->identifier = $identifier;
    }

    /**
     * Gets the mapped identifier field of this class.
     *
     * @return string $identifier
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Get identifier field names of this class.;
     *
     * Since CouchDB only allows exactly one identifier field this is a proxy
     * to {@see getIdentifier()} and returns an array.
     *
     * @return array
     */
    public function getIdentifierFieldNames()
    {
        return array($this->identifier);
    }

    /**
     * Checks whether the class has a (mapped) field with a certain name.
     *
     * @param $fieldName
     * @return boolean
     */
    public function hasField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    /**
     * Registers a custom repository class for the document class.
     *
     * @param string $repositoryClassName  The class name of the custom mapper.
     */
    public function setCustomRepositoryClass($repositoryClassName)
    {
        $this->customRepositoryClassName = $repositoryClassName;
    }

    /**
     * The name of this Document class.
     *
     * @return string $name The Document class name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * The namespace this Document class belongs to.
     *
     * @return string $namespace The namespace name.
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set the field that will contain attachments of this document.
     *
     * @param string $fieldName
     * @throws MappingException
     */
    public function mapAttachments($fieldName)
    {
        if (isset($this->fieldMappings[$fieldName]) || isset($this->associationsMappings[$fieldName])) {
            throw MappingException::duplicateFieldMapping($this->name, $fieldName);
        }

        $this->hasAttachments = true;
        $this->attachmentField = $fieldName;
    }

    /**
     * Map an embedded object
     *
     * - fieldName - The name of the property/field on the mapped php class
     * - jsonName - JSON key name of this field in CouchDB.
     * - targetDocument - Name of the target document
     * - embedded - one or many embedded objects?
     *
     * @param array $mapping
     */
    public function mapEmbedded(array $mapping)
    {
        $mapping = $this->validateAndCompleteReferenceMapping($mapping);

        $this->mapField($mapping);
    }

    /**
     * Map a field.
     *
     * - type - The Doctrine Type of this field.
     * - fieldName - The name of the property/field on the mapped php class
     * - jsonName - JSON key name of this field in CouchDB.
     * - name - The JSON key of this field in the CouchDB document
     * - id - True for an ID field.
     * - strategy - ID Generator strategy when the field is an id-field.
     * - indexed - Is this field indexed for the Doctrine CouchDB repository view
     * - isVersionField - Is this field containing the revision number of this document?
     *
     * @param array $mapping The mapping information.
     */
    public function mapField(array $mapping)
    {
        $mapping = $this->validateAndCompleteFieldMapping($mapping);

        if (!isset($mapping['type'])) {
            $mapping['type'] = "mixed";
        }

        if (isset($mapping['id']) && $mapping['id'] === true) {
            $mapping['type'] = 'string';
            $mapping['jsonName'] = '_id';
            $this->setIdentifier($mapping['fieldName']);
            if (isset($mapping['strategy'])) {
                $this->idGenerator = constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_' . strtoupper($mapping['strategy']));
                unset($mapping['strategy']);
            }
        } else if (isset($mapping['isVersionField'])) {
            $this->isVersioned = true;
            $this->versionField = $mapping['fieldName'];
        }

        $mapping = $this->checkAndStoreIndexMapping($mapping);

        $this->fieldMappings[$mapping['fieldName']] = $mapping;
        $this->jsonNames[$mapping['jsonName']] = $mapping['fieldName'];
    }

    protected function validateAndCompleteFieldMapping($mapping)
    {
        if ( ! isset($mapping['fieldName']) || !$mapping['fieldName']) {
            throw new MappingException("Mapping a property requires to specify the name.");
        }
        if ( ! isset($mapping['jsonName'])) {
            $mapping['jsonName'] = $mapping['fieldName'];
        }
        if (isset($this->fieldMappings[$mapping['fieldName']]) || isset($this->associationsMappings[$mapping['fieldName']])) {
            throw MappingException::duplicateFieldMapping($this->name, $mapping['fieldName']);
        }

        return $mapping;
    }

    protected function validateAndCompleteReferenceMapping($mapping)
    {
        if (isset($mapping['targetDocument']) && $mapping['targetDocument'] && strpos($mapping['targetDocument'], '\\') === false && strlen($this->namespace)) {
            $mapping['targetDocument'] = $this->namespace . '\\' . $mapping['targetDocument'];
        }
        return $mapping;
    }

    protected function validateAndCompleteAssociationMapping($mapping)
    {
        $mapping = $this->validateAndCompleteFieldMapping($mapping);

        $mapping['sourceDocument'] = $this->name;
        $mapping = $this->validateAndCompleteReferenceMapping($mapping);
        return $mapping;
    }

    public function mapManyToOne($mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        $mapping['isOwning'] = true;
        $mapping['type'] = self::MANY_TO_ONE;

        $mapping = $this->checkAndStoreIndexMapping($mapping);

        $this->storeAssociationMapping($mapping);
    }

    public function mapManyToMany($mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        $mapping['isOwning'] = empty($mapping['mappedBy']);
        $mapping['type'] = self::MANY_TO_MANY;

        $this->storeAssociationMapping($mapping);
    }

    private function checkAndStoreIndexMapping($mapping)
    {
        if (isset($mapping['indexed']) && $mapping['indexed']) {
            $this->indexes[] = $mapping['fieldName'];
        }
        unset($mapping['indexed']);

        return $mapping;
    }

    private function storeAssociationMapping($mapping)
    {
        $this->associationsMappings[$mapping['fieldName']] = $mapping;
        $this->jsonNames[$mapping['jsonName']] = $mapping['fieldName'];
    }

    /**
     * A numerically indexed list of field names of this persistent class.
     *
     * This array includes identifier fields if present on this class.
     *
     * @return array
     */
    public function getFieldNames()
    {
        return array_keys($this->fieldMappings);
    }

    /**
     * Gets the mapping of a field.
     *
     * @param string $fieldName  The field name.
     * @return array  The field mapping.
     * @throws MappingException
     */
    public function getFieldMapping($fieldName)
    {
        if ( ! isset($this->fieldMappings[$fieldName])) {
            throw MappingException::mappingNotFound($this->name, $fieldName);
        }
        return $this->fieldMappings[$fieldName];
    }

    /**
     * Gets the type of a field.
     *
     * @param string $fieldName
     * @return Type
     */
    public function getTypeOfField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]) ?
                $this->fieldMappings[$fieldName]['type'] : null;
    }

    /**
     * Checks if the given field is a mapped association for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function hasAssociation($fieldName)
    {
        return isset($this->associationsMappings[$fieldName]);
    }

    public function isCollectionValuedAssociation($name)
    {
        // TODO: included @EmbedMany here also?
        return isset($this->associationsMappings[$name]) && ($this->associationsMappings[$name]['type'] & self::TO_MANY);
    }

    /**
     * Checks if the given field is a mapped single valued association for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function isSingleValuedAssociation($fieldName)
    {
        return isset($this->associationsMappings[$fieldName]) &&
                ($this->associationsMappings[$fieldName]['type'] & self::TO_ONE);
    }

    /**
     * A numerically indexed list of association names of this persistent class.
     *
     * This array includes identifier associations if present on this class.
     *
     * @return array
     */
    public function getAssociationNames()
    {
        return array_keys($this->associationsMappings);
    }

    /**
     * Returns the target class name of the given association.
     *
     * @param string $assocName
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getAssociationTargetClass($assocName)
    {
        if (!isset($this->associationsMappings[$assocName])) {
            throw new \InvalidArgumentException("Association name expected, '" . $assocName ."' is not an association.");
        }
        return $this->associationsMappings[$assocName]['targetDocument'];
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationMappedByTargetField($assocName)
    {
        return $this->associationsMappings[$assocName]['mappedBy'];
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociationInverseSide($assocName)
    {
        return isset($this->associationsMappings[$assocName]) && ! $this->associationsMappings[$assocName];
    }

    public function isInheritedField($field)
    {
        return isset($this->fieldMappings[$field]['declared']);
    }

    public function isInheritedAssociation($field)
    {
        return isset($this->associationsMappings[$field]['declared']);
    }

    public function setParentClasses($classes)
    {
        $this->parentClasses         = $classes;
        $this->inInheritanceHierachy = true;
        if (count($classes) > 0) {
            $this->rootDocumentName = array_pop($classes);
        }
    }

    public function markInheritanceRoot()
    {
        if ($this->parentClasses) {
            throw MappingException::invalidInheritanceRoot($this->name, $this->parentClasses);
        }
        $this->inInheritanceHierachy = true;
    }

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ReflectionService $reflService The reflection service.
     *
     * @return void
     */
    public function initializeReflection($reflService)
    {
        $this->reflClass = $reflService->getClass($this->name);
        $this->namespace = $reflService->getClassNamespace($this->name);

        if ($this->reflClass) {
            $this->name = $this->rootDocumentName = $this->reflClass->getName();
        }
    }
}

