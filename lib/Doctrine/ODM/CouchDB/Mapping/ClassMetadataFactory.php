<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class ClassMetadataFactory
{
    private $metadatas = array();

    public function setMetadataFor(ClassMetadata $metadata)
    {
        $this->metadatas[$metadata->name] = $metadata;
    }

    public function hasMetadataFor($class)
    {
        return isset($this->metadatas[$class]);
    }

    public function getMetadataFor($class)
    {
        if (!isset($this->metadatas[$class])) {
            throw MappingException::classNotMapped();
        }

        return $this->metadatas[$class];
    }
}