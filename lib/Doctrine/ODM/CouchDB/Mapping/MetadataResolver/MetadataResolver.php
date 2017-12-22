<?php


namespace Doctrine\ODM\CouchDB\Mapping\MetadataResolver;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\DocumentManager;

/**
 * Abstraction layer for resolving non-primitive metadata from a CouchDB document.
 */
interface MetadataResolver
{
    public function createDefaultDocumentStruct(ClassMetadata $class);

    public function canMapDocument(array $documentData);

    public function getDocumentType(array $documentData);

    public function canResolveJsonField($jsonName);

    public function resolveJsonField(ClassMetadata $class, DocumentManager $dm, $documentState, $jsonName, $originalData);

    public function storeAssociationField($data, ClassMetadata $class, DocumentManager $dm, $fieldName, $fieldValue);
}
