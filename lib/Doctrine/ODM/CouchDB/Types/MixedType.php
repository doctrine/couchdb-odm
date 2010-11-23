<?php

namespace Doctrine\ODM\CouchDB\Types;

class MixedType extends Type
{
    public function convertToCouchDBValue($value)
    {
        return $value;
    }

    public function convertToPHPValue($value)
    {
        return $value;
    }
}