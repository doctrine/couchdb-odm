<?php

namespace Doctrine\ODM\CouchDB\View;

class FileDesignDocument implements DesignDocument
{
    private $jsonFile;

    public function __construct($file)
    {
        $this->jsonFile = $file;
    }

    public function getData()
    {
        return \json_decode(file_get_contents($this->jsonFile), true);
    }
}