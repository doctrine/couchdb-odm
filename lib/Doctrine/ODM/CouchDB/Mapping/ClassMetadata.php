<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class ClassMetadata
{
    const IDGENERATOR_UUID = 1;
    const IDGENERATOR_ASSIGNED = 2;

    const TO_ONE = 3;
    const TO_MANY = 12;
    const ONE_TO_ONE = 1;
    const ONE_TO_MANY = 2;
    const MANY_TO_ONE = 4;
    const MANY_TO_MANY = 8;

    public $name;

    public $idGenerator = self::IDGENERATOR_ASSIGNED;

    public $properties = array();
    public $resultKeyProperties = array();
    public $associations = array();

    public $reflClass = null;
    public $reflProps = array();

    public $identifier = null;

    public $prototype = null;

    public function __construct($name)
    {
        $this->name = $name;
        $this->reflClass = new \ReflectionClass($name);
    }

    public function mapId($mapping)
    {
        $mapping['resultkey'] = '_id';
        $mapping['type'] = 'string'; // TODO: Really?
        $this->mapProperty($mapping);

        $this->identifier = $mapping['name'];
    }

    public function mapProperty($mapping)
    {
        if (!isset($mapping['name'])) {
            throw new MappingException("Mapping a property requires to specify the name.");
        }

        if (!isset($mapping['resultkey'])) {
            $mapping['resultkey'] = $mapping['name'];
        }

        if (!isset($mapping['type'])) {
            $mapping['type'] = "string";
        }
        $this->properties[$mapping['name']] = $mapping;

        $this->reflProps[$mapping['name']] = $this->reflClass->getProperty($mapping['name']);
        $this->reflProps[$mapping['name']]->setAccessible(true);

        $this->resultKeyProperties[$mapping['resultkey']] = $mapping['name'];
    }

    public function mapManyToOne($mapping)
    {
        if (!isset($mapping['name'])) {
            throw new MappingException("Mapping an association requires to specify the name.");
        }

        $mapping['sourceDocument'] = $this->name;
        if (!isset($mapping['targetDocument'])) {
            throw new MappingException("You have to specify a 'targetDocument' class for the '" . $this->name . "#". $mapping['name']."' association.");
        }
        $mapping['isOwning'] = true;
        $mapping['type'] = self::MANY_TO_ONE;

        $this->associations[$mapping['name']] = $mapping;
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

    /**
     * Extracts the identifier value of an entity of this class.
     *
     * CouchDB has no composite keys which considerably simplifies this method.
     *
     * @param object $doc
     * @return string
     */
    public function getIdentifierValues($doc)
    {
        return $value = $this->reflProps[$this->identifier]->getValue($doc);
    }
}