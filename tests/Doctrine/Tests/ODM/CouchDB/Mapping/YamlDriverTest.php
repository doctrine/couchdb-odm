<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver;

class YamlDriverTest extends AbstractMappingDriverTest
{
    protected function loadDriver()
    {
        return new YamlDriver(array(__DIR__."/yml"));
    }
}