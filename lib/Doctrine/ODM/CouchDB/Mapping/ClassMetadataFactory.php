<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class ClassMetadataFactory
{
    private $metadatas = array();

    /**
     *
     * @param ClassMetadata $metadata
     * @param string $name
     */
    public function setMetadataFor(ClassMetadata $metadata, $name = null)
    {
        $name = $name ?: $metadata->name;

        $this->metadatas[$name] = $metadata;
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