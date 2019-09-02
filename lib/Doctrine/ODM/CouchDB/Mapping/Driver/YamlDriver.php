<?php

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\Common\Persistence\Mapping\Driver\FileDriver,
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\ODM\CouchDB\Mapping\MappingException,
    Doctrine\Common\Persistence\Mapping\MappingException as DoctrineMappingException,
    Symfony\Component\Yaml\Yaml;

/**
 * The YamlDriver reads the mapping metadata from yaml schema files.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class YamlDriver extends FileDriver
{
    const DEFAULT_FILE_EXTENSION = '.dcm.yml';

    /**
     * {@inheritDoc}
     */
    public function __construct($locator, $fileExtension = self::DEFAULT_FILE_EXTENSION)
    {
        parent::__construct($locator, $fileExtension);
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        /** @var $class \Doctrine\ODM\CouchDB\Mapping\ClassMetadata */
        try {
            $element = $this->getElement($className);
        } catch (DoctrineMappingException $e) {
            // Convert Exception type for consistency with other drivers
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$element) {
            return;
        }

        if ($element['type'] == 'document') {
            $class->setCustomRepositoryClass(
                (isset($element['repositoryClass'])) ? $element['repositoryClass'] : null
            );

            if (isset($element['indexed']) && $element['indexed'] == true) {
                $class->indexed = true;
            }

            if (isset($element['inheritanceRoot']) && $element['inheritanceRoot']) {
                $class->markInheritanceRoot();
            }
        } else if ($element['type'] == 'embedded') {
            $class->isEmbeddedDocument = true;

            if (isset($element['inheritanceRoot']) && $element['inheritanceRoot']) {
                $class->markInheritanceRoot();
            }
        } else if (strtolower($element['type']) == "mappedsuperclass") {
            $class->isMappedSuperclass = true;
        } else {
            throw MappingException::classIsNotAValidDocument($className);
        }

        if (isset($element['id'])) {
            foreach ($element['id'] AS $fieldName => $idElement) {
                $class->mapField(array(
                    'fieldName' => $fieldName,
                    'indexed'   => (isset($idElement['index'])) ? (bool)$idElement['index'] : false,
                    'type'      => (isset($idElement['type'])) ? $idElement['type'] : null,
                    'id'        => true,
                    'strategy'  => (isset($idElement['strategy'])) ? $idElement['strategy'] :  null,
                ));
            }
        }

        if (isset($element['fields'])) {
            foreach ($element['fields'] AS $fieldName => $fieldElement) {
                $class->mapField(array(
                    'fieldName' => $fieldName,
                    'jsonName'  => (isset($fieldElement['jsonName'])) ? $fieldElement['jsonName'] : null,
                    'indexed'   => (isset($fieldElement['index'])) ? (bool)$fieldElement['index'] : false,
                    'type'      => (isset($fieldElement['type'])) ? $fieldElement['type'] : null,
                    'isVersionField' => (isset($fieldElement['version'])) ? true : null,
                ));
            }
        }


        if (isset($element['referenceOne'])) {
            foreach ($element['referenceOne'] AS $field => $referenceOneElement) {
                $class->mapManyToOne(array(
                    'cascade'           => (isset($referenceOneElement['cascade'])) ? $this->getCascadeMode($referenceOneElement['cascade']) : 0,
                    'targetDocument'    => (string)$referenceOneElement['targetDocument'],
                    'fieldName'         => $field,
                    'jsonName'          => (isset($referenceOneElement['jsonName'])) ? (string)$referenceOneElement['jsonName'] : null,
                    'indexed'           => (isset($referenceOneElement['index'])) ? (bool)$referenceOneElement['index'] : false,
                ));
            }
        }

        if (isset($element['referenceMany'])) {
            foreach ($element['referenceMany'] AS $field => $referenceManyElement) {
                $class->mapManyToMany(array(
                    'cascade'           => (isset($referenceManyElement['cascade'])) ? $this->getCascadeMode($referenceManyElement['cascade']) : 0,
                    'targetDocument'    => (string)$referenceManyElement['targetDocument'],
                    'fieldName'         => $field,
                    'jsonName'          => (isset($referenceManyElement['jsonName'])) ? (string)$referenceManyElement['jsonName'] : null,
                    'mappedBy'          => (isset($referenceManyElement['mappedBy'])) ? (string)$referenceManyElement['mappedBy'] : null,
                ));
            }
        }

        if (isset($element['attachments'])) {
            $class->mapAttachments($element['attachments']);
        }

        if (isset($element['embedOne'])) {
            foreach ($element['embedOne'] AS $field => $embedOneElement) {
                $class->mapEmbedded(array(
                    'targetDocument'    => (string)$embedOneElement['targetDocument'],
                    'fieldName'         => $field,
                    'jsonName'          => (isset($embedOneElement['jsonName'])) ? (string)$embedOneElement['jsonName'] : null,
                    'embedded'          => 'one',
                ));
            }
        }

        if (isset($element['embedMany'])) {
            foreach ($element['embedMany'] AS $field => $embedManyElement) {
                $class->mapEmbedded(array(
                    'targetDocument'    => (string)$embedManyElement['targetDocument'],
                    'fieldName'         => $field,
                    'jsonName'          => (isset($embedManyElement['jsonName'])) ? (string)$embedManyElement['jsonName'] : null,
                    'embedded'          => 'many',
                ));
            }
        }
    }

    protected function loadMappingFile($file)
    {
        return Yaml::parse(file_get_contents($file));
    }

    /**
     * Gathers a list of cascade options found in the given cascade element.
     *
     * @param array $cascadeElement The cascade element.
     * @return integer a bitmask of cascade options.
     * @throws MappingException
     */
    private function getCascadeMode(array $cascadeElement)
    {
        $cascade = 0;
        foreach ($cascadeElement as $cascadeMode) {
            $constantName = 'Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode);
            if (!defined($constantName)) {
                throw new MappingException("Cascade mode '$cascadeMode' not supported.");
            }
            $cascade |= constant($constantName);
        }

        return $cascade;
    }
}
