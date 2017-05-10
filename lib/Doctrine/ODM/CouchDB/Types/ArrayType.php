<?php

namespace Doctrine\ODM\CouchDB\Types;

class ArrayType extends Type
{
    public function convertToCouchDBValue($value)
    {
        return (array)$value;
    }

    public function convertToPHPValue($value)
    {
        return (array)$value;
    }
}
