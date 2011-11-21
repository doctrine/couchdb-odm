<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Tests\Models\Embedded\Embedded;
use Doctrine\Tests\Models\Embedded\Embedder;
use Doctrine\Tests\Models\Embedded\Nested;

class EmbedNestedTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp() 
    {
        $this->type = 'Doctrine\Tests\Models\Embedded\Embedder';
        $this->embeddedType = 'Doctrine\Tests\Models\Embedded\Embedded';
        $this->nestedType = 'Doctrine\Tests\Models\Embedded\Nested';
        $this->dm = $this->createDocumentManager();

        $httpClient = $this->dm->getHttpClient();
        $data = json_encode(
            array(
                '_id' => "1",
                'embedded' => array(
                    'name' => 'embedded one',
                    'embeds' => array(
                        array(
                            'nestedName' => 'nested one',
                            'type' => $this->nestedType
                            ),
                        array(
                            'nestedName' => 'nested two',
                            'type' => $this->nestedType
                            )
                        ),
                    'type' => $this->embeddedType
                    ),
                'type' => $this->type
                ));
        $resp = $httpClient->request('PUT', '/' . $this->dm->getDatabase() . '/1', $data);
        $this->assertEquals(201, $resp->status);
    }
    
    public function testFind() 
    {
        $embedder = $this->dm->find($this->type, 1);
        $this->assertNotNull($embedder);
        $this->assertInstanceOf($this->type, $embedder);
        $embedded = $embedder->embedded;
        $this->assertNotNull($embedded);
        $this->assertInstanceOf($this->embeddedType, $embedded);

        $nesteds = $embedded->embeds;
        $this->assertEquals(2, count($nesteds));

        $nested = $nesteds[0];
        $this->assertInstanceOf($this->nestedType, $nested);
        $this->assertEquals('nested one', $nested->nestedName);
        $nested = $nesteds[1];
        $this->assertInstanceOf($this->nestedType, $nested);
        $this->assertEquals('nested two', $nested->nestedName);

    }

}


