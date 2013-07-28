<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional\Ticket;

use Doctrine\Tests\Models\CMS\CmsUser;

/**
 * @group GH-70
 */
class GH70Test extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    public function testIssue()
    {
        $user = new CmsUser();
        $user->username = "foo";
        $user->name = "Foo";
        $user->status = "active";

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->getReference('Doctrine\Tests\Models\CMS\CmsUser', $user->id);
        $user->getUsername();

        $user->username = 'bar';

        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\CMS\CmsUser', $user->id);

        $this->assertEquals('bar', $user->username);
    }
}
