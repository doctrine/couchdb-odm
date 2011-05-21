<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\Models\Embedded\Embedder;
use Doctrine\ODM\CouchDB\UnitOfWork;

class DetachTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }
    
    public function testDetachNewObject()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->detach($user);
        
        $this->assertEquals(UnitOfWork::STATE_NEW, $this->dm->getUnitOfWork()->getDocumentState($user));
    }
    
    public function testDetachedKnownObject()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->persist($user);
        $this->dm->flush();
        
        $this->dm->detach($user);
        
        $this->assertEquals(UnitOfWork::STATE_DETACHED, $this->dm->getUnitOfWork()->getDocumentState($user));
    }
}