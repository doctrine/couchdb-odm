<?php

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Persistence\Mapping\ClassMetadata AS IClassMetadata,
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
class ClassMetadata extends ClassMetadataInfo implements IClassMetadata
{
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
     * The prototype from which new instances of the mapped class are created.
     *
     * @var object
     */
    private $prototype;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-document mapping
     * metadata of the class with the given name.
     *
     * @param string $documentName The name of the document class the new instance is used for.
     */
    public function __construct($documentName)
    {
        parent::__construct($documentName);
        $this->reflClass = new \ReflectionClass($documentName);
        $this->name = $this->reflClass->getName();
        $this->namespace = $this->reflClass->getNamespaceName();
    }

    /**
     * Used to derive a class metadata of the current instance for a mapped child class.
     *
     * @param  string $childName
     * @return ClassMetadata
     */
    public function deriveChildMetadata($childName)
    {
        if (!is_subclass_of($childName, $this->name)) {
            throw new \InvalidArgumentException("Only child class names of '".$this->name."' are valid values.");
        }

        $cm = clone $this;
        /* @var $cm ClassMetadata */
        $cm->reflClass = new \ReflectionClass($childName);
        $cm->name = $cm->reflClass->getName();
        $cm->namespace = $cm->reflClass->getNamespaceName();

        if ($this->isMappedSuperclass) {
            $cm->rootDocumentName = $cm->name;
        }

        $cm->isMappedSuperclass = false;
        $cm->isEmbeddedDocument = false;

        foreach ($cm->fieldMappings AS $fieldName => $fieldMapping) {
            if (!isset($fieldMapping['declared'])) {
                $cm->fieldMappings[$fieldName]['declared'] = $this->name;
            }
        }
        foreach ($cm->associationsMappings AS $assocName => $assocMapping) {
            if (!isset($assocMapping['declared'])) {
                $cm->associationsMappings[$assocName]['declared'] = $this->name;
            }
        }
        if ($cm->attachmentField && !$cm->attachmentDeclaredClass) {
            $cm->attachmentDeclaredClass = $this->name;
        }

        return $cm;
    }

    protected function validateAndCompleteFieldMapping($mapping)
    {
        $mapping = parent::validateAndCompleteFieldMapping($mapping);

        $reflProp = $this->reflClass->getProperty($mapping['fieldName']);
        $reflProp->setAccessible(true);
        $this->reflFields[$mapping['fieldName']] = $reflProp;

        return $mapping;
    }

    public function mapAttachments($fieldName)
    {
        parent::mapAttachments($fieldName);

        $this->reflFields[$fieldName] = $this->reflClass->getProperty($fieldName);
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
}
