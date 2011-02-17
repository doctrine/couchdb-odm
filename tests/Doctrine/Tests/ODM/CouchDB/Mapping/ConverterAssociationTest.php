<?php
namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Attachment;
use Doctrine\ODM\CouchDB\Mapping\Converter;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\Tests\Models\CMS\CmsArticle;
use Doctrine\Tests\Models\CMS\CmsGroup;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\Models\CMS\CmsUserRights;
use Doctrine\Tests\Models\CMS\CmsAddress;

/**
 * @group converter
 */
class ConverterAssociationsTest extends AbstractConverterTest
{
    public function setUp()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration;
        $config->setMetadataDriverImpl($this->loadDriverForCMSDocuments());
            

        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($config);
        $this->uow = new UnitOfWorkMock($dm);

        $this->groupOneData = array(
            '_id' =>'group1',
            '_rev'=>'1',
            'name'=>'Group One',
            'doctrine_metadata'=>array('type'=>'Doctrine\Tests\Models\CMS\CmsGroup')
            );
        $this->groupTwoData = array(
            '_id' =>'group2',
            '_rev'=>'1',
            'name'=>'Group Two',
            'doctrine_metadata'=>array('type'=>'Doctrine\Tests\Models\CMS\CmsGroup')
            );
        $this->userOneData = array(
            '_id' =>'user1',
            '_rev'=>'1',
            'status'=>'active',
            'username'=>'user1',
            'name'=>'User 1',
            '_attachments'=>array(
                'avatar'=>array(
                    'content_type'=>'image/png',
                    'revpos'=>1,
                    'length'=>2345,
                    'stub'=>true
                    ),
                'vcard'=>array(
                    'content_type'=>'text/x-vcard',
                    'revpos'=>1,
                    'length'=>2345,
                    'stub'=>true
                    )
                ),
            'doctrine_metadata'=>array(
                'type'=>'Doctrine\Tests\Models\CMS\CmsUser',
                'associations'=>array(
                    'rights'=>'rights-1',
                    'groups'=>array('group1')
                    )
                )
            );
        $this->userOneTwoData = array(
            '_id' =>'user12',
            '_rev'=>'1',
            'status'=>'active',
            'username'=>'user12',
            'name'=>'User 1-2',
            'doctrine_metadata'=>array(
                'type'=>'Doctrine\Tests\Models\CMS\CmsUser',
                'associations'=>array(
                    'rights'=>null,
                    'groups'=>null
                    )
                )
            );        
    }

    public function testUpdateActualStateAttachments()
    {
        $article = new CmsArticle;
        $article->id = 'article1';
        $article->attachments = array();
        $article->attachments['123'] = Attachment::createFromBinaryData('123');
        $article->attachments['456'] = Attachment::createFromBinaryData('456');

        $converter = new Converter($article, 'Doctrine\Tests\Models\CMS\CmsArticle', $this->uow);
        $converter->updateActualState();
        $meta = $converter->getActualMetadata();
        $this->assertArrayHasKey('_attachments', $meta);
        $this->assertArrayHasKey('123', $meta['_attachments']);
        $this->assertArrayHasKey('123', $meta['_attachments']);
        $this->assertEquals(2, count($meta['_attachments']));
        
    }

    public function testUpdateActualStateAssociations()
    {
        $user1 = new CmsUser;
        $user1->id = 'user1';
        $user1->status = 'active';
        $user1->username = 'user1';
        $user1->name = 'User 1';
        
        $group1 = new CmsGroup;
        $group1->name = 'Group One';
        $group1->id = 'group1';

        $group2 = new CmsGroup;
        $group2->name = 'Group Two';
        $group2->id = 'group2';

        $user1->name= 'User 1 modified';
        $user1->addGroup($group1);
        $user1->addGroup($group2);

        $address = new CmsAddress;
        $address->country = 'Hungary';
        $address->zip = '1111';
        $address->city = 'Budapest';
        $address->street = 'Street';
        $user1->address = $address;

        $article = new CmsArticle;
        $article->id = 'article1';
        $article->topic = 'topic';
        $article->text = 'article text';

        $user1->addArticle($article);

        $rights = new CmsUserRights;
        $rights->id = 'rights1';

        $user1->rights = $rights;
        
        $className = 'Doctrine\Tests\Models\CMS\CmsUser';
        $converter = new Converter($user1, $className, $this->uow);
        $converter->updateActualState();
        $this->assertEquals(array(
                         '_id' =>'user1',
                         'status'=>'active',
                         'username'=>'user1',
                         'name'=>'User 1 modified'),
                            $converter->getActualData()
            );
        $this->assertEquals(1, count($converter->getActualChildConverters()));
        $this->assertArrayHasKey('address', $converter->getActualChildConverters());

        // ------------
        $actualMeta = $converter->getActualMetadata();
        $this->assertArrayHasKey('doctrine_metadata', $actualMeta);
        $this->assertArrayHasKey('associations', $actualMeta['doctrine_metadata']);
        $associations = $actualMeta['doctrine_metadata']['associations'];
        $this->assertEquals(2, count($associations));
        $this->assertArrayHasKey('rights', $associations);
        $this->assertArrayHasKey('groups', $associations);

    }


    public function testInverseAssocitions()
    {
        $className = 'Doctrine\Tests\Models\CMS\CmsGroup';
        $converter = $this->createConverter($className);

        $converter->refresh($this->groupOneData);
        $groupOne = $converter->getInstance();
        $this->assertEquals('Group One', $groupOne->getName());
        $users = $groupOne->getUsers();
        $this->assertinstanceof('Doctrine\ODM\CouchDB\PersistentViewCollection', $users);
        $this->assertFalse($users->isInitialized);
        
    }

    public function testAttachmentMapping()
    {
        $className = 'Doctrine\Tests\Models\CMS\CmsUser';
        $converter = $this->createConverter($className);

        $converter->refresh($this->userOneData);
        $userOne = $converter->getInstance();

        $this->assertNotNull($userOne->attachments);
        $this->assertEquals(2, count($userOne->attachments));
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Attachment', $userOne->attachments['avatar']);
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Attachment', $userOne->attachments['vcard']);
    }

    public function testManyToManyAssociations()
    {
        $className = 'Doctrine\Tests\Models\CMS\CmsUser';
        $converter = $this->createConverter($className);

        $converter->refresh($this->userOneData);
        $userOne = $converter->getInstance();
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentIdsCollection', $userOne->groups);
        $this->assertFalse($userOne->groups->isInitialized);
        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Proxy\Proxy', $userOne->rights);
        $this->assertFalse($userOne->rights->__isInitialized__);

        $converter = $this->createConverter($className);
        $converter->refresh($this->userOneTwoData);
        $userOneTwo = $converter->getInstance();

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\PersistentIdsCollection', $userOneTwo->groups);
        $this->assertTrue($userOneTwo->groups->isInitialized);
        $this->assertNull($userOneTwo->rights);
    }
    
}