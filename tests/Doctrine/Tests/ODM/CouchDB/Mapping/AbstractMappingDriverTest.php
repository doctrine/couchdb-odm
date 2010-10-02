<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata,
    Doctrine\ODM\CouchDB\Mapping\Driver\XmlDriver,
    Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver;

abstract class AbstractMappingDriverTest extends \PHPUnit_Framework_Testcase
{
    abstract protected function loadDriver();

    public function testLoadMapping()
    {
        $className = __NAMESPACE__.'\User';
        $mappingDriver = $this->loadDriver();

        $class = new ClassMetadata($className);
        $mappingDriver->loadMetadataForClass($className, $class);

        return $class;
    }

    /**
     * @depends testDocumentCollectionNameAndInheritance
     * @param ClassMetadata $class
     */
    public function testFieldMappings($class)
    {
        $this->assertEquals(7, count($class->fieldMappings));
        $this->assertTrue(isset($class->fieldMappings['id']));
        $this->assertTrue(isset($class->fieldMappings['jsonName']));
        $this->assertTrue(isset($class->fieldMappings['email']));

        return $class;
    }

    /**
     * @depends testDocumentCollectionNameAndInheritance
     * @param ClassMetadata $class
     */
    public function testStringFieldMappings($class)
    {
        $this->assertEquals('string', $class->fieldMappings['jsonName']['type']);

        return $class;
    }

    /**
     * @depends testFieldMappings
     * @param ClassMetadata $class
     */
    public function testIdentifier($class)
    {
        $this->assertEquals('_id', $class->identifier);

        return $class;
    }

    /**
     * @depends testIdentifier
     * @param ClassMetadata $class
     */
    public function testAssocations($class)
    {
        $this->assertEquals(7, count($class->fieldMappings));

        return $class;
    }

    /**
     * @depends testAssocations
     * @param ClassMetadata $class
     */
    public function testOwningOneToOneAssocation($class)
    {
        $this->assertTrue(isset($class->fieldMappings['address']));
        $this->assertTrue(is_array($class->fieldMappings['address']));

        return $class;
    }

    /**
     * @depends testLifecycleCallbacksSupportMultipleMethodNames
     * @param ClassMetadata $class
     */
    public function testCustomFieldName($class)
    {
        $this->assertEquals('jsonName', $class->fieldMappings['jsonName']['fieldName']);
        $this->assertEquals('username', $class->fieldMappings['jsonName']['jsonName']);

        return $class;
    }
}

/**
 * @Document
 */
class User
{
    /**
     * @Id
     */
    public $id;

    /**
     * @String(name="username")
     */
    public $name;

    /**
     * @String
     */
    public $email;

    /**
     * @Int
     */
    public $mysqlProfileId;

    /**
     * @ReferenceOne(targetDocument="Address", cascade={"remove"})
     */
    public $address;

    /**
     * @ReferenceMany(targetDocument="Phonenumber", cascade={"persist"})
     */
    public $phonenumbers;

    /**
     * @ReferenceMany(targetDocument="Group", cascade={"all"})
     */
    public $groups;

    /**
     * @PrePersist
     */
    public function doStuffOnPrePersist()
    {
    }

    /**
     * @PrePersist
     */
    public function doOtherStuffOnPrePersistToo()
    {
    }

    /**
     * @PostPersist
     */
    public function doStuffOnPostPersist()
    {
    }

    public static function loadMetadata(ClassMetadata $metadata)
    {
        throw new \BadMethodCallException();
    }
}