<?php


namespace Doctrine\ODM\CouchDB\Mapping\MetadataResolver;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\PersistentIdsCollection;
use Doctrine\Common\Collections\ArrayCollection;

class DoctrineResolver implements MetadataResolver
{
    public function createDefaultDocumentStruct(ClassMetadata $class)
    {
        $struct = array('type' => str_replace("\\", ".", $class->name));
        if ($class->indexed) {
            $struct['doctrine_metadata']['indexed'] = true;
        }
        if ($class->indexes) {
            $struct['doctrine_metadata']['indexes'] = $class->indexes;
        }
        return $struct;
    }

    public function canResolveJsonField($jsonName)
    {
        return ($jsonName === 'doctrine_metadata');
    }

    public function getDocumentType(array $documentData)
    {
        return (str_replace(".", "\\", $documentData['type']));
    }

    public function resolveJsonField(ClassMetadata $class, DocumentManager $dm, $documentState, $jsonName, $originalData)
    {
        $uow = $dm->getUnitOfWork();
        $couchClient = $dm->getCouchDBClient();

        if ($jsonName == 'doctrine_metadata' && isset($originalData['doctrine_metadata']['associations'])) {
            foreach ($originalData['doctrine_metadata']['associations'] AS $assocName) {
                $assocValue = $originalData[$assocName];
                if (isset($class->associationsMappings[$assocName])) {
                    if ($class->associationsMappings[$assocName]['type'] & ClassMetadata::TO_ONE) {
                        if ($assocValue) {
                            if ($class->associationsMappings[$assocName]['targetDocument'] &&
                                $dm->getClassMetadata($class->associationsMappings[$assocName]['targetDocument'])->inInheritanceHierachy) {

                                $assocValue = $dm->getReference($class->associationsMappings[$assocName]['targetDocument'], $assocValue);
                            } else {
                                $response = $couchClient->findDocument($assocValue);

                                if ($response->status == 404) {
                                    $assocValue = null;
                                } else {
                                    $hints = array();
                                    $assocValue = $uow->createDocument(null, $response->body, $hints);
                                }
                            }
                        }
                        $documentState[$class->associationsMappings[$assocName]['fieldName']] = $assocValue;
                    } else if ($class->associationsMappings[$assocName]['type'] & ClassMetadata::MANY_TO_MANY) {
                        if ($class->associationsMappings[$assocName]['isOwning']) {
                            $documentState[$class->associationsMappings[$assocName]['fieldName']] = new PersistentIdsCollection(
                                new ArrayCollection(),
                                $class->associationsMappings[$assocName]['targetDocument'],
                                $dm,
                                $assocValue
                            );
                        }
                    }
                }
            }
        }
        return $documentState;
    }

    public function canMapDocument(array $documentData)
    {
        return isset($documentData['type']);
    }

    public function storeAssociationField($data, ClassMetadata $class, DocumentManager $dm, $fieldName, $fieldValue)
    {
        $data['doctrine_metadata']['associations'][] = $fieldName;
        $data[$fieldName] = $fieldValue;
        return $data;
    }
}
