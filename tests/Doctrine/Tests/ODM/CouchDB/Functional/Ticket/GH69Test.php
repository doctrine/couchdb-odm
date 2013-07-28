<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional\Ticket;

/**
 * @group GH-69
 */
class GH69Test extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testIssue()
    {
        $metadata = $this->dm->getClassMetadata(__NAMESPACE__ . '\\GH69User');

        $this->assertEquals(array('username'), $metadata->indexes);
    }
}

/**
 * @MappedSuperclass
 */
class GH69UserBase
{
    /**
     * @Id
     */
    public $id;

    /**
     * @Field(type="string") @Index
     */
    public $username;
}

/**
 * @Document
 */
class GH69User extends GH69UserBase
{
}
