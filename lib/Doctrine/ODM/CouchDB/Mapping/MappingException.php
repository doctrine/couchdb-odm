<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class MappingException extends \Exception
{
    static public function classNotMapped()
    {
        return new self();
    }

    static public function noTypeSpecified()
    {
        return new self();
    }
}