<?php

namespace Doctrine\ODM\CouchDB\Types;

class TypeException extends \Doctrine\ODM\CouchDB\CouchDBException
{
    static public function unknownType($name)
    {
        return new self("Using an unknown type '" . $name . "'.");
    }

    static public function typeExists($name)
    {
        return new self("Cannot add type with name '" . $name . "', it already exists.");
    }

    static public function typeNotFound($name)
    {
        return new self("Cannot overwrite '" . $name ."', because it is not a known type yet.");
    }
}
