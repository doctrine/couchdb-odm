<?php

namespace Doctrine\Tests\Models\LifecycleCallbacks;

use Doctrine\ODM\CouchDB\Mapping\Annotations\Field;
use Doctrine\ODM\CouchDB\Mapping\Annotations\HasLifecycleCallbacks;
use Doctrine\ODM\CouchDB\Mapping\Annotations\MappedSuperclass;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PostLoad;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PostPersist;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PostRemove;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PostUpdate;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PrePersist;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PreRemove;
use Doctrine\ODM\CouchDB\Mapping\Annotations\PreUpdate;

/**
 * @MappedSuperclass()
 * @HasLifecycleCallbacks()
 */
abstract class CallbackDocument
{
    /**
     * @Field(type="string")
     */
    public $name;

    /**
     * @Field(type="datetime")
     */
    public $createdAt;

    /**
     * @Field(type="datetime")
     */
    public $updatedAt;

    public $prePersist = false;
    public $postPersist = false;
    public $preUpdate = false;
    public $postUpdate = false;
    public $preRemove = false;
    public $postRemove = false;
    public $preLoad = false;
    public $postLoad = false;
    public $preFlush = false;

    /**
     * @PrePersist()
     */
    public function prePersist()
    {
        $this->prePersist = true;
        $this->createdAt = new \DateTime();
    }

    /**
     * @PostPersist()
     */
    public function postPersist()
    {
        $this->postPersist = true;
    }

    /**
     * @PreUpdate()
     */
    public function preUpdate()
    {
        $this->preUpdate = true;
        $this->updatedAt = new \DateTime();
    }

    /**
     * @PostUpdate()
     */
    public function postUpdate()
    {
        $this->postUpdate = true;
    }

    /**
     * @PreRemove()
     */
    public function preRemove()
    {
        $this->preRemove = true;
    }

    /**
     * @PostRemove()
     */
    public function postRemove()
    {
        $this->postRemove = true;
    }

    /**
     * @PreLoad
     */
    public function preLoad()
    {
        $this->preLoad = true;
    }

    /**
     * @PostLoad()
     */
    public function postLoad()
    {
        $this->postLoad = true;
    }

    /**
     * @PreFlush
     */
    public function preFlush()
    {
        $this->preFlush = true;
    }
}
