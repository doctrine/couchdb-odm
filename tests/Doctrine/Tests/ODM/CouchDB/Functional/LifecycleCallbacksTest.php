<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\Tests\Models\LifecycleCallbacks\CallbackProfile;
use Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser;
use Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase;

class LifecycleCallbacksTest extends CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $this->dm = $this->createDocumentManager();
    }

    private function createUser()
    {
        $user = new CallbackUser();
        $user->name = 'jon';
        $user->profile = new CallbackProfile();
        $user->profile->name = 'Jonathan H. Wage';
        $this->dm->persist($user);
        $this->dm->flush();

        return $user;
    }

    public function testPreUpdateChangingValue()
    {
        $user = $this->createUser();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser', $user->id);
        $this->assertTrue($user->preUpdate);
        $this->assertInstanceOf('DateTime', $user->createdAt);
        $this->assertInstanceOf('DateTime', $user->profile->createdAt);

        $user->name = 'jon changed';
        $user->profile->name = 'changed';
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser', $user->id);
        $this->assertInstanceOf('DateTime', $user->updatedAt);
        $this->assertInstanceOf('DateTime', $user->profile->updatedAt);
    }

    public function testPreAndPostPersist()
    {
        $user = $this->createUser();
        $this->assertTrue($user->prePersist);
        $this->assertTrue($user->profile->prePersist);

        $this->assertTrue($user->postPersist);
        $this->assertTrue($user->profile->postPersist);
    }

    public function testPreUpdate()
    {
        $user = $this->createUser();
        $user->name = 'jwage';
        $user->profile->name = 'Jon Doe';
        $this->dm->flush();

        $this->assertTrue($user->preUpdate);
        $this->assertTrue($user->profile->preUpdate);

        $this->assertTrue($user->postUpdate);
        $this->assertTrue($user->profile->postUpdate);
    }

    public function testPreFlush()
    {
        $user = $this->createUser();
        $user->name = 'jwage';
        $user->profile->name = 'Jon Doe';
        $this->dm->flush();

        $this->assertTrue($user->preFlush);
        $this->assertTrue($user->profile->preFlush);
    }

    public function testPreLoadAndPostLoad()
    {
        $user = $this->createUser();
        $this->dm->clear();

        $user = $this->dm->find('Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser', $user->id);

        $this->assertTrue($user->preLoad);
        $this->assertTrue($user->profile->preLoad);
        $this->assertTrue($user->postLoad);
        $this->assertTrue($user->profile->postLoad);
    }

    public function testPreAndPostRemove()
    {
        $user = $this->createUser();
        $this->dm->remove($user);
        $this->dm->flush();

        $this->assertTrue($user->preRemove);
        $this->assertTrue($user->profile->preRemove);

        $this->assertTrue($user->postRemove);
        $this->assertTrue($user->profile->postRemove);
    }

    public function testEmbedManyEvent()
    {
        $user = new CallbackUser();
        $user->name = 'jon';

        $profile = new CallbackProfile();
        $profile->name = 'testing cool ya';
        $user->profiles[] = $profile;

        $this->dm->persist($user);
        $this->dm->flush();

        $this->assertTrue($profile->prePersist);
        $this->assertTrue($profile->postPersist);
        $this->assertFalse($profile->preUpdate);
        $this->assertFalse($profile->postUpdate);

        $profile->name = 'changed';
        $this->dm->flush();

        $this->assertTrue($profile->preUpdate);
        $this->assertTrue($profile->postUpdate);

        $this->dm->clear();
        $user = $this->dm->find('Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser', $user->id);
        $profile = $user->profiles[0];

        $this->assertTrue($profile->preLoad);
        $this->assertTrue($profile->postLoad);

        $profile->name = 'w00t';
        $this->dm->flush();

        $this->assertTrue($user->preUpdate);
        $this->assertTrue($user->postUpdate);
        $this->assertTrue($profile->preUpdate);
        $this->assertTrue($profile->postUpdate);

        $this->dm->remove($user);
        $this->dm->flush();

        $this->assertTrue($user->preRemove);
        $this->assertTrue($user->postRemove);
        $this->assertTrue($profile->preRemove);
        $this->assertTrue($profile->postRemove);
    }

    public function testMultipleLevelsOfEmbedded()
    {
        $user = $this->createUser();
        $profile = new CallbackProfile();
        $profile->name = '2nd level';
        $user->profile->profile = $profile;
        $this->dm->flush();

        $this->assertTrue($profile->prePersist);
        $this->assertTrue($profile->postPersist);
        $this->assertFalse($profile->preUpdate);
        $this->assertFalse($profile->postUpdate);

        $profile->name = '2nd level changed';
        $this->dm->flush();

        $this->assertTrue($profile->preUpdate);
        $this->assertTrue($profile->postUpdate);

        $this->dm->clear();
        $user = $this->dm->find('Doctrine\Tests\Models\LifecycleCallbacks\CallbackUser', $user->id);
        $profile = $user->profile->profile;
        $profile->name = '2nd level changed again';

        $profile2 = new CallbackProfile();
        $profile2->name = 'test';
        $user->profiles[] = $profile2;
        $this->dm->flush();

        $this->assertFalse($profile->prePersist);
        $this->assertFalse($profile->postPersist);
        $this->assertTrue($profile->preUpdate);
        $this->assertTrue($profile->postUpdate);

        $this->assertTrue($profile2->prePersist);
        $this->assertTrue($profile2->postPersist);
        $this->assertFalse($profile2->preUpdate);
        $this->assertFalse($profile2->postUpdate);

        $this->dm->remove($user);
        $this->dm->flush();

        $this->assertTrue($user->preRemove);
        $this->assertTrue($user->postRemove);

        $this->assertTrue($user->profile->preRemove);
        $this->assertTrue($user->profile->postRemove);

        $this->assertTrue($user->profile->profile->preRemove);
        $this->assertTrue($user->profile->profile->postRemove);

        $this->assertTrue($user->profiles[0]->preRemove);
        $this->assertTrue($user->profiles[0]->postRemove);
    }
}
