<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class EmbedNested extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp() 
    {
        $this->type = 'Doctrine\Tests\ODM\CouchDB\Functional\Embedder';
        $this->embeddedType = 'Doctrine\Tests\ODM\CouchDB\Functional\Embedded';
        $this->nestedType = 'Doctrine\Tests\ODM\CouchDB\Functional\Nested';
        $this->dm = $this->createDocumentManager();

        $httpClient = $this->dm->getConfiguration()->getHttpClient();
        $data = json_encode(
            array(
                '_id' => "1",
                'embedded' => array(
                    'name' => 'embedded one',
                    'embeds' => array(
                        array(
                            'nestedName' => 'nested one',
                            'doctrine_metadata' => array('type' => $this->nestedType)
                            ),
                        array(
                            'nestedName' => 'nested two',
                            'doctrine_metadata' => array('type' => $this->nestedType)
                            )
                        ),
                    'doctrine_metadata' => array('type' => $this->embeddedType)
                    ),
                'doctrine_metadata' => array('type' => $this->type)
                ));
        $resp = $httpClient->request('PUT', '/' . $this->dm->getConfiguration()->getDatabase() . '/1', $data);
        $this->assertEquals(201, $resp->status);
    }
    
    public function testFind() 
    {
        $embedder = $this->dm->find($this->type, 1);
        $this->assertNotNull($embedder);
        $this->assertType($this->type, $embedder);
        $embedded = $embedder->embedded;
        $this->assertNotNull($embedded);
        $this->assertType($this->embeddedType, $embedded);

        $nesteds = $embedded->embeds;
        $this->assertEquals(2, count($nesteds));
        $nested = $nesteds[0];
        $this->assertType($this->nestedType, $nested);
        $this->assertEquals('nested one', $nested->nestedName);
        $nested = $nesteds[1];
        $this->assertType($this->nestedType, $nested);
        $this->assertEquals('nested two', $nested->nestedName);

    }

}

/**
 * @Document
 */
class Embedder {
    /**
     * @Id(strategy="ASSIGNED")
     */
    public $id;

    /**
     * @EmbedOne(targetDocument="Doctrine\Tests\ODM\CouchDB\Functional\Embedded")
     */
    public $embedded;
}

/**
 * @EmbeddedDocument
 */
class Embedded {
    /**
     * @Field
     */
    public $name;

    /**
     * @EmbedMany(targetDocument="Doctrine\Tests\ODM\CouchDB\Functional\Nested")
     */
    public $embeds = array();    
}

/**
 * @EmbeddedDocument
 */
class Nested {
    /**
     * @Field
     */
    public $nestedName;
}


