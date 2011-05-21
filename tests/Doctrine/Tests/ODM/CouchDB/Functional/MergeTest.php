<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\Models\Embedded\Embedder;

class MergeTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }
    
    public function testMergeNewDocument()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $mergedUser = $this->dm->merge($user);
        
        $this->assertNotSame($mergedUser, $user);
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $mergedUser);
        $this->assertEquals("beberlei", $mergedUser->username);
        $this->assertEquals(32, strlen($mergedUser->id), "Merged new document should have generated UUID");
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentCollection', $mergedUser->groups);
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentCollection', $mergedUser->articles);
    }
    
    public function testMergeManagedDocument()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->persist($user);
        $this->dm->flush();
        
        $mergedUser = $this->dm->merge($user);
     
        $this->assertSame($mergedUser, $user);
    }
    
    public function testMergeKnownDocument()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();
        
        $mergedUser = $this->dm->merge($user);
     
        $this->assertNotSame($mergedUser, $user);
        $this->assertSame($mergedUser->id, $user->id);
    }
    
    public function testMergeRemovedDocument()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->persist($user);
        $this->dm->flush();
        
        $this->dm->remove($user);
        
        $this->setExpectedException('InvalidArgumentException', 'Removed document detected during merge. Can not merge with a removed document.');
        $this->dm->merge($user);
    }
    
    public function testMergeWithManagedDocument()
    {
        $user = new CmsUser();
        $user->username = "beberlei";
        $user->name = "Benjamin";
        
        $this->dm->persist($user);
        $this->dm->flush();
        
        $mergableUser = new CmsUser();
        $mergableUser->id = $user->id;
        $mergableUser->username = "jgalt";
        $mergableUser->name = "John Galt";
        
        $mergedUser = $this->dm->merge($mergableUser);
        
        $this->assertSame($mergedUser, $user);
        $this->assertEquals("jgalt", $mergedUser->username);
    }
    
    public function testMergeUnknownAssignedId()
    {
        $doc = new Embedder;
        $doc->id = "foo";
        $doc->name = "Foo";
        
        $mergedDoc = $this->dm->merge($doc);
        
        $this->assertNotSame($mergedDoc, $doc);
        $this->assertSame($mergedDoc->id, $doc->id);
    }
}