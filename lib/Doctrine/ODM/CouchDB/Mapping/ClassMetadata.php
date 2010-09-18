<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class ClassMetadata
{
    public $name;

    public $properties = array();

    public $reflClass = null;
    public $reflProps = array();

    public $identifier = array();

    public $prototype = null;

    public function __construct($name)
    {
        $this->name = $name;
        $this->reflClass = new \ReflectionClass($name);
    }

    public function mapProperty($mapping)
    {
        if (!isset($mapping['type'])) {
            $mapping['type'] = "string";
        }
        $this->properties[$mapping['name']] = $mapping;

        if (isset($mapping['id'])) {
            $this->identifier[] = $mapping['name'];
        }

        $this->reflProps[$mapping['name']] = $this->reflClass->getProperty($mapping['name']);
        $this->reflProps[$mapping['name']]->setAccessible(true);
    }

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
}