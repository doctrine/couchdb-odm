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

    public function testEmbedManyWithoutTargetDocuments()
    {
        $className = 'Doctrine\Tests\XML\EmbedMany';
        $mappingDriver = $this->loadDriver();

        $class = new ClassMetadata($className);
        $class->namespace = 'Doctrine\Tests\XML';
        $mappingDriver->loadMetadataForClass($className, $class);

        $this->assertTrue(isset($class->fieldMappings['embeddedField']));
        $this->assertArrayHasKey(
            'targetDocument',
            $class->fieldMappings['embeddedField']
        );
        $this->assertNull($class->fieldMappings['embeddedField']['targetDocument']);
    }
}
