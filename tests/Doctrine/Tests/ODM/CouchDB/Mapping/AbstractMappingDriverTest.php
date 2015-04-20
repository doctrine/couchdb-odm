<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata,
    Doctrine\ODM\CouchDB\Mapping\Driver\XmlDriver,
    Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver;

abstract class AbstractMappingDriverTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function loadDriver();

    public function testLoadMapping()
    {
        $className = 'Doctrine\Tests\Models\CMS\CmsUser';
        $mappingDriver = $this->loadDriver();

        $class = new ClassMetadata($className);
        $class->namespace = 'Doctrine\Tests\Models\CMS';
        $mappingDriver->loadMetadataForClass($className, $class);

        return $class;
    }

    /**
     * @depends testLoadMapping
     * @param ClassMetadata $class
     */
    public function testFieldMappings($class)
    {
        $this->assertEquals(5, count($class->fieldMappings));
        $this->assertTrue(isset($class->fieldMappings['id']));
        $this->assertTrue(isset($class->fieldMappings['name']));
        $this->assertTrue(isset($class->fieldMappings['username']));
        $this->assertTrue(isset($class->fieldMappings['status']));
        $this->assertTrue(isset($class->fieldMappings['address']));

        return $class;
    }

    /**
     * @depends testFieldMappings
     * @param ClassMetadata $class
     */
    public function testStringFieldMappings($class)
    {
        $this->assertEquals('string', $class->fieldMappings['name']['type']);

        return $class;
    }

    /**
     * @depends testFieldMappings
     * @param ClassMetadata $class
     */
    public function testIdentifier($class)
    {
        $this->assertEquals('id', $class->identifier);
        $this->assertEquals(ClassMetadata::IDGENERATOR_UUID, $class->idGenerator);

        return $class;
    }

    /**
     * @depends testFieldMappings
     * @param ClassMetadata $class
     */
    public function testManyToOneAssociationMapping($class)
    {
        $this->assertArrayHasKey('rights', $class->associationsMappings);

        $this->assertEquals(array(
            'fieldName' => 'rights',
            'cascade' => 0,
            'jsonName' => 'rights',
            'targetDocument' => 'Doctrine\Tests\Models\CMS\CmsUserRights',
            'sourceDocument' => 'Doctrine\Tests\Models\CMS\CmsUser',
            'isOwning' => true,
            'type' => ClassMetadata::MANY_TO_ONE,
        ), $class->associationsMappings['rights']);

        return $class;
    }

    /**
     * @depends testFieldMappings
     * @param ClassMetadata $class
     */
    public function testManyToManyAssociationMapping($class)
    {
        $this->assertArrayHasKey('groups', $class->associationsMappings);

        $this->assertEquals(array(
            'fieldName' => 'groups',
            'cascade' => 0,
            'mappedBy' => null,
            'targetDocument' => 'Doctrine\Tests\Models\CMS\CmsGroup',
            'jsonName' => 'groups',
            'sourceDocument' => 'Doctrine\Tests\Models\CMS\CmsUser',
            'isOwning' => true,
            'type' => ClassMetadata::MANY_TO_MANY,
        ), $class->associationsMappings['groups']);

        return $class;
    }

    /**
     * @depends testManyToManyAssociationMapping
     * @param ClassMetadata $class
     */
    public function testAttachmentMapping($class)
    {
        $this->assertTrue($class->hasAttachments);
        $this->assertEquals('attachments', $class->attachmentField);

        return $class;
    }
    
    /**
     * @depends testManyToManyAssociationMapping
     * @param ClassMetadata $class
     */
    public function testEmbeddedMapping($class)
    {
        $this->assertArrayHasKey('address', $class->fieldMappings);
        $this->assertEquals(array(
            'fieldName' => 'address',
            'jsonName' => 'address',
            'embedded' => 'one',
            'targetDocument' => '',
            'type' => 'mixed',
        ), $class->fieldMappings['address']);
    }

    public function testVersionMapping()
    {
        $className = 'Doctrine\Tests\Models\CMS\CmsArticle';
        $mappingDriver = $this->loadDriver();

        $class = new ClassMetadata($className);
        $class->namespace = 'Doctrine\Tests\Models\CMS';
        $mappingDriver->loadMetadataForClass($className, $class);

        $this->assertEquals(array(
            'fieldName' => 'version',
            'jsonName' => '_rev',
            'type' => 'string',
            'isVersionField' => true,
        ), $class->fieldMappings['version']);
    }
}
