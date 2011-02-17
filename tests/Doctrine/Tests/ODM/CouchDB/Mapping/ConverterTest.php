<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

/**
 * @group converter
 */
class ConverterTest extends AbstractConverterTest
{

    public function setUp()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration;
        $config->setMetadataDriverImpl($this->loadDriver());
            

        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($config);
        $this->uow = new UnitOfWorkMock($dm);
    }

    public function testEmbeddedEntity()
    {
        $className = 'Doctrine\Tests\ODM\CouchDB\Mapping\EmbedderEntity';
        $converter = $this->createConverter($className);
        
        $data = array(
            'doctrine_metadata'=>array('type'=>$className),
            'name'=>'foo',
            'embeddedOne'=>array(
                'doctrine_metadata'=>array('type'=>'Doctrine\Tests\ODM\CouchDB\Mapping\EmbeddedEntity'),
                'name'=>'egy'
                ),
            'embeddedMany'=>array(
                'egy'=>array(
                    'doctrine_metadata'=>array('type'=>'Doctrine\Tests\ODM\CouchDB\Mapping\EmbeddedEntity'),
                    'name'=>'many_egy'
                    ),
                'ketto'=>array(
                    'doctrine_metadata'=>array('type'=>'Doctrine\Tests\ODM\CouchDB\Mapping\EmbeddedEntity'),
                    'name'=>'many_ketto'
                    )
                )
            );

        $converter->refresh($data);
        $i = $converter->getInstance();
        $this->assertNotNull($i->embeddedOne);
        $this->assertEquals('egy', $i->embeddedOne->name);

        $this->assertNotNull($i->embeddedMany);
        $this->assertNotEmpty($i->embeddedMany);
        $this->assertEquals('many_egy', $i->embeddedMany['egy']->name);
        $this->assertEquals('many_ketto', $i->embeddedMany['ketto']->name);

        $ccs = $converter->getChildConverters();
        $this->assertNotNull($ccs['embeddedOne']);
        $this->assertNotNull($ccs['embeddedMany']);
    }

    public function testSimpleEntity()
    {
        $className = 'Doctrine\Tests\ODM\CouchDB\Mapping\SimpleEntity';
        $converter = $this->createConverter($className);
        
        $data = array(
            'doctrine_metadata'=>array('type'=>$className),
            '_id' => '123',
            '_rev' => '456',
            'name' => 'foo',
            'nonmapped' => 'data',
            'nonmapped_array' => array('1', '2')
            );

        $converter->refresh($data);
        $i = $converter->getInstance();
        $this->assertEquals('123', $i->id);
        $this->assertEquals('456', $i->version);
        $this->assertEquals('foo', $i->name);

        $this->assertEquals(array('nonmapped'=>'data', 'nonmapped_array' => array('1', '2')),
                            $converter->getNonMappedData());

        $metadata = $converter->getMetadata();
//        $this->assertEquals('456', $metadata['_rev']);

    }


}

/**
 * @Document
 */
class SimpleEntity
{
    /**
     * @Id
     */
    public $id;
    
    /**
     * @Version
     */
    public $version;
    
    /**
     * @Field
     */
    public $name;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
    
    public function getVersion()
    {
        return $this->version;
    }
    
    public function setVersion($version)
    {
        $this->version = $version;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setId($id)
    {
        $this->id = $id;
    }
}

/**
 * @Document
 */
class EmbedderEntity
{
    /**
     * @Id
     */
    public $id;
    
    /**
     * @EmbedOne(targetDocument="Doctrine\Tests\ODM\CouchDB\Mapping\EmbeddedEntity")
     */
    public $embeddedOne;
    
    /**
     * @EmbedMany(targetDocument="Doctrine\Tests\ODM\CouchDB\Mapping\EmbeddedEntity")
     */
    public $embeddedMany = array();

    /**
     * @Field
     */
    private $name;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
    
    public function getEmbeddedMany()
    {
        return $this->embeddedMany;
    }
    
    public function setEmbeddedMany($embeddedMany)
    {
        $this->embeddedMany = $embeddedMany;
    }
    
    public function getEmbeddedOne()
    {
        return $this->embeddedOne;
    }
    
    public function setEmbeddedOne($embeddedOne)
    {
        $this->embeddedOne = $embeddedOne;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setId($id)
    {
        $this->id = $id;
    }

}


/**
 * @EmbeddedDocument
 */
class EmbeddedEntity
{
    /**
     * @Field
     */
    public $name;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
}

