<?php

namespace Doctrine\ODM\CouchDB\Types;

class ObjectType extends Type
{
    public function convertToCouchDBValue($value)
    {
        return (object)$value;
    }

    public function convertToPHPValue($value)
    {
        return (object)$value;
    }
}
