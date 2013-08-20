<?php

namespace Doctrine\ODM\CouchDB\Types;

abstract class Type
{
    const MIXED = 'mixed';
    const TARRAY = 'array';
    const BOOLEAN = 'boolean';
    const DATETIME = 'datetime';
    const FLOAT = 'float';
    const INTEGER = 'integer';
    const OBJECT = 'object';
    const STRING = 'string';

    /** Map of already instantiated type objects. One instance per type (flyweight). */
    private static $_typeObjects = array();

    /** The map of supported doctrine mapping types. */
    private static $_typesMap = array(
        self::MIXED => 'Doctrine\ODM\CouchDB\Types\MixedType',
        self::BOOLEAN => 'Doctrine\ODM\CouchDB\Types\BooleanType',
        self::INTEGER => 'Doctrine\ODM\CouchDB\Types\IntegerType',
        self::STRING => 'Doctrine\ODM\CouchDB\Types\StringType',
        self::FLOAT => 'Doctrine\ODM\CouchDB\Types\FloatType',
        self::DATETIME => 'Doctrine\ODM\CouchDB\Types\DateTimeType',
    );

    protected function __construct() {}

    /**
     * @param mixed $value
     * @return mixed
     */
    abstract public function convertToCouchDBValue($value);

    /**
     * @param mixed $value
     * @return mixed
     */
    abstract public function convertToPHPValue($value);

    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @static
     * @throws TypeException
     * @param string $name The name of the type (as returned by getName()).
     * @return \Doctrine\ODM\CouchDB\Types\Type
     */
    public static function getType($name)
    {
        if ( ! isset(self::$_typeObjects[$name])) {
            if ( ! isset(self::$_typesMap[$name])) {
                throw TypeException::unknownType($name);
            }
            self::$_typeObjects[$name] = new self::$_typesMap[$name]();
        }

        return self::$_typeObjects[$name];
    }

    /**
     * Adds a custom type to the type map.
     *
     * @static
     * @param string $name Name of the type. This should correspond to what getName() returns.
     * @param string $className The class name of the custom type.
     * @throws \Doctrine\ODM\CouchDB\CouchDBException
     */
    public static function addType($name, $className)
    {
        if (isset(self::$_typesMap[$name])) {
            throw TypeException::typeExists($name);
        }

        self::$_typesMap[$name] = $className;
    }

    /**
     * Checks if exists support for a type.
     *
     * @static
     * @param string $name Name of the type
     * @return boolean TRUE if type is supported; FALSE otherwise
     */
    public static function hasType($name)
    {
        return isset(self::$_typesMap[$name]);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @static
     * @param string $name
     * @param string $className
     * @throws \Doctrine\ODM\CouchDB\CouchDBException
     */
    public static function overrideType($name, $className)
    {
        if ( ! isset(self::$_typesMap[$name])) {
            throw TypeException::typeNotFound($name);
        }

        self::$_typesMap[$name] = $className;
    }

    /**
     * Get the types array map which holds all registered types and the corresponding
     * type class
     *
     * @return array $typesMap
     */
    public static function getTypesMap()
    {
        return self::$_typesMap;
    }

    public function __toString()
    {
        $e = explode('\\', get_class($this));
        return str_replace('Type', '', end($e));
    }
}
