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
        $this->assertTrue(isset($class->fieldMappings['name']));
        $this->assertTrue(isset($class->fieldMappings['email']));

        return $class;
    }

    /**
     * @depends testDocumentCollectionNameAndInheritance
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
        $this->assertEquals('name', $class->fieldMappings['name']['fieldName']);
        $this->assertEquals('username', $class->fieldMappings['name']['name']);

        return $class;
    }
}

/**
 * @Document(collection="cms_users")
 * @HasLifecycleCallbacks
 */
class User
{
    /**
     * @Id
     */
    public $id;

    /**
     * @String(name="username")
     * @Index(order="desc")
     */
    public $name;

    /**
     * @String
     * @UniqueIndex(order="desc", dropDups="true")
     */
    public $email;

    /**
     * @Int
     * @UniqueIndex(order="desc", dropDups="true")
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
        $metadata->mapField(array(
           'id' => true,
           'fieldName' => 'id',
          ));
        $metadata->mapField(array(
           'fieldName' => 'name',
           'name' => 'username',
           'type' => 'string'
          ));
        $metadata->mapField(array(
           'fieldName' => 'email',
           'type' => 'string'
          ));
          $metadata->mapField(array(
             'fieldName' => 'mysqlProfileId',
             'type' => 'integer'
            ));
        $metadata->mapOneReference(array(
           'fieldName' => 'address',
           'targetDocument' => 'Doctrine\\ODM\\CouchDB\\Tests\\Mapping\\Address',
           'cascade' => 
           array(
           0 => 'remove',
           )
          ));
        $metadata->mapManyReference(array(
           'fieldName' => 'phonenumbers',
           'targetDocument' => 'Doctrine\\ODM\\CouchDB\\Tests\\Mapping\\Phonenumber',
           'cascade' => 
           array(
           1 => 'persist',
           )
          ));
        $metadata->mapManyReference(array(
           'fieldName' => 'groups',
           'targetDocument' => 'Doctrine\\ODM\\CouchDB\\Tests\\Mapping\\Group',
           'cascade' => 
           array(
           0 => 'remove',
           1 => 'persist',
           2 => 'refresh',
           3 => 'merge',
           4 => 'detach',
           ),
          ));
    }
}