<?php

namespace Doctrine\ODM\CouchDB\Mapping;

/**
 * Metadata class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class ClassMetadata
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
    private $identifier;

    /**
     * READ-ONLY: The name of the document class.
     */
    public $name;

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
     * The ReflectionProperty instances of the mapped class.
     *
     * @var array
     */
    public $reflFields = array();

    /**
     * The prototype from which new instances of the mapped class are created.
     *
     * @var object
     */
    private $prototype;

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
     * The ReflectionClass instance of the mapped class.
     *
     * @var ReflectionClass
     */
    public $reflClass;

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
     * Initializes a new ClassMetadata instance that will hold the object-document mapping
     * metadata of the class with the given name.
     *
     * @param string $documentName The name of the document class the new instance is used for.
     */
    public function __construct($documentName)
    {
        $this->name = $documentName;
        $this->rootDocumentName = $documentName;
        $this->reflClass = new \ReflectionClass($documentName);
        $this->namespace = $this->reflClass->getNamespaceName();
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
     * @param array $identifier
     */
    public function setIdentifier($identifier)
    {
        if ($this->isEmbeddedDocument) {
            throw new MappingException('EmbeddedDocument should not have id field');
        }
        $this->identifier = $identifier;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Checks whether the class has a (mapped) field with a certain name.
     *
     * @return boolean
     */
    public function hasField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    /**
     * Registers a custom repository class for the document class.
     *
     * @param string $mapperClassName  The class name of the custom mapper.
     */
    public function setCustomRepositoryClass($repositoryClassName)
    {
        $this->customRepositoryClassName = $repositoryClassName;
    }

    /**
     * Gets the ReflectionClass instance of the mapped class.
     *
     * @return ReflectionClass
     */
    public function getReflectionClass()
    {
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
     */
    public function mapAttachments($fieldName)
    {
        if (isset($this->fieldMappings[$fieldName]) || isset($this->associationsMappings[$fieldName])) {
            throw MappingException::duplicateFieldMapping($this->name, $fieldName);
        }

        $this->hasAttachments = true;
        $this->attachmentField = $fieldName;
        $this->reflFields[$fieldName] = $this->reflClass->getProperty($fieldName);
    }

    /**
     * Map a field.
     *
     * - type - The Doctrine Type of this field.
     * - fieldName - The name of the property/field on the mapped php class
     * - name - The JSON key of this field in the CouchDB document
     * - id - True for an ID field.
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
            }
        } else if (isset($mapping['isVersionField'])) {
            $this->isVersioned = true;
            $this->versionField = $mapping['fieldName'];
        }

        if (isset($mapping['indexed']) && $mapping['indexed']) {
            $this->indexes[] = $mapping['fieldName'];
        }

        $this->fieldMappings[$mapping['fieldName']] = $mapping;
        $this->jsonNames[$mapping['jsonName']] = $mapping['fieldName'];
    }

    protected function validateAndCompleteFieldMapping($mapping)
    {
        if ( ! isset($mapping['fieldName'])) {
            throw new MappingException("Mapping a property requires to specify the name.");
        }
        if ( ! isset($mapping['jsonName'])) {
            $mapping['jsonName'] = $mapping['fieldName'];
        }
        if (isset($this->fieldMappings[$mapping['fieldName']]) || isset($this->associationsMappings[$mapping['fieldName']])) {
            throw MappingException::duplicateFieldMapping($this->name, $mapping['fieldName']);
        }
        $reflProp = $this->reflClass->getProperty($mapping['fieldName']);
        $reflProp->setAccessible(true);
        $this->reflFields[$mapping['fieldName']] = $reflProp;

        return $mapping;
    }

    protected function validateAndCompleteAssociationMapping($mapping)
    {
        $mapping = $this->validateAndCompleteFieldMapping($mapping);

        $mapping['sourceDocument'] = $this->name;
        if (!isset($mapping['targetDocument'])) {
            throw new MappingException("You have to specify a 'targetDocument' class for the '" . $this->name . "#". $mapping['jsonName']."' association.");
        }
        if (isset($mapping['targetDocument']) && strpos($mapping['targetDocument'], '\\') === false && strlen($this->namespace)) {
            $mapping['targetDocument'] = $this->namespace . '\\' . $mapping['targetDocument'];
        }
        return $mapping;
    }

    public function mapManyToOne($mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        $mapping['isOwning'] = true;
        $mapping['type'] = self::MANY_TO_ONE;

        $this->storeAssociationMapping($mapping);
    }

    public function mapManyToMany($mapping)
    {
        $mapping = $this->validateAndCompleteAssociationMapping($mapping);

        if (!empty($mapping['mappedBy'])) {
            $mapping['isOwning'] = false;
        } else {
            $mapping['isOwning'] = true;
        }
        $mapping['type'] = self::MANY_TO_MANY;

        $this->storeAssociationMapping($mapping);
    }

    private function storeAssociationMapping($mapping)
    {
        $this->associationsMappings[$mapping['fieldName']] = $mapping;
        $this->jsonNames[$mapping['jsonName']] = $mapping['fieldName'];
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
     * Gets the mapping of a field.
     *
     * @param string $fieldName  The field name.
     * @return array  The field mapping.
     */
    public function getFieldMapping($fieldName)
    {
        if ( ! isset($this->fieldMappings[$fieldName])) {
            throw MappingException::mappingNotFound($this->name, $fieldName);
        }
        return $this->fieldMappings[$fieldName];
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @return object
     */
    public function newInstance()
    {
        if ($this->prototype === null) {
            $this->prototype = unserialize(
                sprintf(
                    'O:%d:"%s":0:{}',
                    strlen($this->name),
                    $this->name
                )
            );
        }
        return clone $this->prototype;
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
            'alsoLoadMethods',
            'associationsMappings',
            'fieldMappings',
            'jsonNames',
            'idGenerator',
            'identifier',
            'rootDocumentName',
        );

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
        }

        if ($this->isEmbeddedDocument) {
            $serialized[] = 'isEmbeddedDocument';
        }

        if ($this->isReadOnly) {
            $serialized[] = 'isReadOnly';
        }

        if ($this->isMappedSuperclass) {
            $serialized[] = 'isMappedSuperclass';
        }

        return $serialized;
    }

    /**
     * Restores some state that can not be serialized/unserialized.
     *
     * @return void
     */
    public function __wakeup()
    {
        // Restore ReflectionClass and properties
        $this->reflClass = new \ReflectionClass($this->name);
        $this->namespace = $this->reflClass->getNamespaceName();

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
            // TODO: doesnt support inheritance
            $reflField = $this->reflClass->getProperty($this->attachmentField);
            $reflField->setAccessible(true);
            $this->reflFields[$this->attachmentField] = $reflField;
        }
    }

    public function isCollectionValuedAssociation($name)
    {
        // TODO: included @EmbedMany here also?
        return isset($this->associationsMappings[$name]) && ($this->associationsMappings[$name]['type'] & self::TO_MANY);
    }
}
