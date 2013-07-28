<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional\Ticket;

class GH71Test extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testMetadata()
    {
        $metadata = $this->dm->getClassMetadata(__NAMESPACE__ . '\\GH71Invoice');

        $this->assertEquals(array('user'), $metadata->indexes);
    }

    public function testIssue()
    {
        $user = new GH71User();
        $invoice = new GH71Invoice();
        $invoice->user = $user;

        $this->dm->persist($user);
        $this->dm->persist($invoice);
        $this->dm->flush();
        $this->dm->clear();

        $repository = $this->dm->getRepository(__NAMESPACE__ . '\\GH71Invoice');
        $invoices = $repository->findBy(array('user' => $user->id));

        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id, $invoices[0]->id);
    }
}

/**
 * @Document
 */
class GH71Invoice
{
    /**
     * @Id
     */
    public $id;

    /**
     * @ReferenceOne(targetDocument="GH71User")
     * @Index
     */
    public $user;
}

/**
 * @Document
 */
class GH71User
{
    /**
     * @Id
     */
    public $id;
}
