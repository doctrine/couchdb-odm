<?php


namespace Doctrine\ODM\CouchDB;

class InvalidDocumentTypeException extends CouchDBException
{
    public function __construct($class, $type)
    {
        parent::__construct("The class '" . $class . "' is not of the expected type '" . $type . "'.");
    }
}
