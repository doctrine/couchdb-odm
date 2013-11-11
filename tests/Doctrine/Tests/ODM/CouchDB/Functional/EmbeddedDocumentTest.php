<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Common\EventSubscriber;

class EmbeddedAssociationTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $userId;
    private $groupIds = array();
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();

        $user1 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user1->username = "beberlei";
        $user1->status = "active";
        $user1->name = "Benjamin";

        $user2 = new \Doctrine\Tests\Models\CMS\CmsUser();
        $user2->username = "lsmith";
        $user2->status = "active";
        $user2->name = "Lukas";

        $address1 = new \Doctrine\Tests\Models\CMS\CmsAddress();
        $address1->country = "Hungary";
        $address1->zip = "1122";
        $address1->city = "Budapest";
        $address1->mainAddress = true;

        $user1->setAddress($address1);

        $this->dm->persist($user1);
        $this->dm->persist($user2);

        $this->dm->flush();
        $this->dm->clear();

        $this->userId = $user1->id;
    }

    public function testShouldNotSaveUnchanged()
    {
        $listener = new PreUpdateSubscriber;
        $this->dm->getEventManager()->addEventListener('preUpdate', $listener);

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userId);
        $this->assertInstanceOf('\Doctrine\Tests\Models\CMS\CmsAddress', $user->address);
        $this->assertEquals('Hungary', $user->address->country);
        $this->assertEquals('1122', $user->address->zip);
        $this->assertEquals('Budapest', $user->address->city);

        $this->dm->flush();

        $this->assertEquals(0, count($listener->eventArgs));
    }

    public function testSaveModifiedEmbedded()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userId);
        $this->assertInstanceOf('\Doctrine\Tests\Models\CMS\CmsAddress', $user->address);
        $this->assertEquals('Hungary', $user->address->country);
        $this->assertEquals('1122', $user->address->zip);
        $this->assertEquals('Budapest', $user->address->city);

        $user->address->country = "Spain";
        $user->address->zip = "1234";
        $user->address->city = "Cartagena";

        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals('Spain', $user->address->country);
        $this->assertEquals('1234', $user->address->zip);
        $this->assertEquals('Cartagena', $user->address->city);

    }

    public function testSaveEmbedded()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userId);
        $this->assertInstanceOf('\Doctrine\Tests\Models\CMS\CmsAddress', $user->address);
        $this->assertEquals('Hungary', $user->address->country);
        $this->assertEquals('1122', $user->address->zip);
        $this->assertEquals('Budapest', $user->address->city);

        $address3 = new \Doctrine\Tests\Models\CMS\CmsAddress();
        $address3->country = "Spain";
        $address3->zip = "1234";
        $address3->city = "Cartagena";
        $user->setAddress($address3);

        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $this->assertEquals('Spain', $user->address->country);
        $this->assertEquals('1234', $user->address->zip);
        $this->assertEquals('Cartagena', $user->address->city);
    }

    /**
     * @group GH-80
     */
    public function testUpdateBooleanToFalseInEmbeddedDocument()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userId);

        $this->assertTrue($user->address->mainAddress);

        $user->address->mainAddress = false;

        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);

        $this->assertFalse($user->address->mainAddress);
    }

    /**
     * @group GH-80
     */
    public function testEmptyStringInEmbeddedDocument()
    {
        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $this->userId);
        $user->address->street = "";

        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);

        $this->assertEquals("", $user->address->street);
    }
}

class PreUpdateSubscriber2 implements EventSubscriber
{
    public $eventArgs = array();
    public function getSubscribedEvents()
    {
        return array(\Doctrine\ODM\CouchDB\Event::preUpdate);
    }

    public function preUpdate(\Doctrine\ODM\CouchDB\Event\LifecycleEventArgs $args)
    {
        $this->eventArgs[] = $args;
    }

}
