<?php

namespace Doctrine\ODM\CouchDB\Types;

class BooleanType extends Type
{
    public function convertToCouchDBValue($value)
    {
        return (bool) $value;
    }

    public function convertToPHPValue($value)
    {
        return (bool) $value;
    }
}