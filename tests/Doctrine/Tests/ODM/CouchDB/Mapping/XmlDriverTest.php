<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Mapping\Driver\XmlDriver;

class XmlDriverTest extends AbstractMappingDriverTest
{
    protected function loadDriver()
    {
        return new XmlDriver(array(__DIR__."/xml"));
    }
}