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

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Types\Type;
use Doctrine\Common\Util\ClassUtils;

/**
 * Helper class serializing/unserializing embedded documents.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Bartfai Tamas <bartfaitamas@gmail.com>
 */
class EmbeddedDocumentSerializer
{
    private $metadataFactory;

    private $metadataResolver;

    public function __construct($metadataFactory, $metadataResolver)
    {
        $this->metadataFactory = $metadataFactory;
        $this->metadataResolver = $metadataResolver;
    }

    /**
     * Serializes an embedded document value into array given the mapping
     * metadata for the class.
     *
     * @param object $embeddedValue
     * @param array $embeddedFieldMapping
     * @param $embedMany
     * @return array
     * @throws \InvalidArgumentException
     */
    public function serializeEmbeddedDocument($embeddedValue, $embeddedFieldMapping, $embedMany = false)
    {
        if ($embeddedValue === null) {
            return null;
        }

        if (!$embedMany && 'many' == $embeddedFieldMapping['embedded'] && (is_array($embeddedValue) || $embeddedValue instanceof \Traversable)) {
            $data = array();
            foreach ($embeddedValue as $key => $val) {
                $data[$key] = $this->serializeEmbeddedDocument($val, $embeddedFieldMapping, true);
            }
        } else {
            $embeddedClass = null;
            if (isset($embeddedFieldMapping['targetDocument'])) {
                $embeddedClass = $this->metadataFactory->getMetadataFor(ClassUtils::getClass($embeddedValue));

                if ($embeddedClass->name !== $embeddedFieldMapping['targetDocument'] &&
                    !is_subclass_of($embeddedClass->name, $embeddedFieldMapping['targetDocument']) ) {

                    throw new \InvalidArgumentException(
                        'Mismatching metadata description in the EmbeddedDocument, expected class ' .
                        $embeddedFieldMapping['targetDocument'] . ' but got ' . get_class($embeddedValue)
                    );
                }
            } else {
                $embeddedClass = $this->metadataFactory->getMetadataFor(get_class($embeddedValue));
            }

            $data = $this->metadataResolver->createDefaultDocumentStruct($embeddedClass);
            foreach($embeddedClass->reflFields AS $fieldName => $reflProperty) {
                $value = $reflProperty->getValue($embeddedValue);
                $fieldMapping = $embeddedClass->fieldMappings[$fieldName];

                if ($value === null) {
                    continue;
                } else if (isset($fieldMapping['embedded'])) {
                    $data[$fieldMapping['jsonName']] = $this->serializeEmbeddedDocument($value, $fieldMapping);
                } else {
                    $data[$fieldMapping['jsonName']] = Type::getType($fieldMapping['type'])
                        ->convertToCouchDBValue($value);
                }
            }
        }
        return $data;
    }

    /**
     * Create a document for an embedded document field mapping from json data.
     *
     * @param array $data
     * @param object $embeddedFieldMapping
     * @return object
     * @throws \InvalidArgumentException
     */
    public function createEmbeddedDocument($data, $embeddedFieldMapping)
    {
        if ($data === null) {
            return null;
        } else if (!is_array($data)) {
            throw new \InvalidArgumentException("Cannot hydrate embedded if the data given is not an array");
        }

        if ('many' == $embeddedFieldMapping['embedded']) {

            $result = array();
            foreach ($data as $jsonName => $jsonValue) {
                if (!is_array($jsonValue)) {
                    throw new \InvalidArgumentException("Cannot hydrate many embedded if the data given is not an array");
                }

                $result[$jsonName] = $this->doCreateEmbeddedDocument($jsonValue, $embeddedFieldMapping);
            }
            ksort($result);
            return new ArrayCollection($result);
        } else {
            return $this->doCreateEmbeddedDocument($data, $embeddedFieldMapping);
        }
    }

    public function doCreateEmbeddedDocument($data, $embeddedFieldMapping)
    {
        if (!$this->metadataResolver->canMapDocument($data)) {
            if (!isset($embeddedFieldMapping['targetDocument'])) {
                throw new \InvalidArgumentException("Missing or missmatching metadata description in the EmbeddedDocument, cannot hydrate!");
            }
            $type = $embeddedFieldMapping['targetDocument'];
        } else {
            $type = $this->metadataResolver->getDocumentType($data);
        }

        $class = $this->metadataFactory->getMetadataFor($type);
        $instance = $class->newInstance();

        $documentState = array();
        foreach ($data as $jsonName => $jsonValue) {
            if ($this->metadataResolver->canResolveJsonField($jsonName)) {
                continue;
            }
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    if ($jsonValue === null) {
                        $fieldValue = null;
                    } else if (isset($class->fieldMappings[$fieldName]['embedded'])) {
                        $fieldValue = $this->createEmbeddedDocument($jsonValue, $class->fieldMappings[$fieldName]);
                    } else {
                        $fieldValue =
                            Type::getType($class->fieldMappings[$fieldName]['type'])
                            ->convertToPHPValue($jsonValue);
                    }

                    $class->setFieldValue($instance,
                                          $class->fieldMappings[$fieldName]['fieldName'],
                                          $fieldValue);


                }
            } else {
                //$nonMappedData[$jsonName] = $jsonValue;
            }
        }
        return $instance;
    }


    /**
     * Compares the two representation of an embedded document.
     *
     * If the original misses doctrine_metadata, but the values are the same, we assume there is no change
     * If the original has doctrine_metadata, and the new value has different class, that's a change,
     * even if the values are the same.
     *
     * @param array $value
     * @param object $originalData
     * @param array $valueFieldMapping Mapping of the field that contains the embedded document in the embedder document.
     * @return boolean
     */
    public function isChanged($value, $originalData, $valueFieldMapping)
    {
        // EmbedMany case
        if ('many' == $valueFieldMapping['embedded'] && (is_array($value) || $value instanceof \Doctrine\Common\Collections\ArrayCollection)) {
            if (count($originalData) != count($value)) {
                return true;
            }
            foreach ($value as $key => $valueElement) {
                if (!isset($originalData[$key])
                    || $this->isChanged($valueElement, $originalData[$key], $valueFieldMapping)) {
                    return true;
                }
            }
            return false;
        }

        // EmbedOne case, or one instance of and EmbedMany
        if ($this->metadataResolver->canMapDocument($originalData)
            && get_class($value) !== $this->metadataResolver->getDocumentType($originalData)) {
            return true;
        }

        $class = $this->metadataFactory->getMetadataFor(get_class($value));
        foreach ($class->reflFields as $fieldName => $fieldValue) {
            $fieldMapping = $class->fieldMappings[$fieldName];
            $originalDataValue = isset($originalData[$fieldMapping['jsonName']])
                ? $originalData[$fieldMapping['jsonName']]
                : null;

            $currentValue = $class->getFieldValue($value, $fieldMapping['fieldName']);

            if ($originalDataValue === null && $currentValue === null) {
                continue;
            } else if ($originalDataValue === null || $currentValue === null) {
                return true;
            }

            if (!isset($fieldMapping['embedded'])) {
                // simple property comparison
                // TODO this conversion could be avoided if we store the php value in the original data
                //      as with the simple property mapping in UOW.
                $originalValue = Type::getType($fieldMapping['type'])
                    ->convertToPHPValue($originalDataValue);
                if ($originalValue != $currentValue) {
                    return true;
                }
            } else {

                if ('many' == $fieldMapping['embedded']) {
                    if (count($originalDataValue) != count($currentValue)) {
                        return true;
                    }
                    foreach ($currentValue as $currentKey => $currentElem) {
                        if (!isset($originalDataValue[$currentKey])) {
                            return true;
                        }
                        if ($this->isChanged($currentElem, $originalDataValue[$currentKey], $fieldMapping)) {
                            return true;
                        }
                    }
                } else { // embedOne
                    if ($this->isChanged($currentValue, $originalDataValue, $fieldMapping)) {
                        return true;
                    }
                }

            }
        }
        return false;
    }
}
