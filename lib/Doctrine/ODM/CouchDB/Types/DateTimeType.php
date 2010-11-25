<?php

namespace Doctrine\ODM\CouchDB\Types;

use DateTime;

class DateTimeType extends Type
{
    public function convertToCouchDBValue($value)
    {
        return $value->format('Y-m-d H:i:s.u');
    }

    public function convertToPHPValue($value)
    {
        return DateTime::createFromFormat('Y-m-d H:i:s.u', $value);
    }
}