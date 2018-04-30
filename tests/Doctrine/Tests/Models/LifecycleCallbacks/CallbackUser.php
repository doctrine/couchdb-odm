<?php

namespace Doctrine\Tests\Models\LifecycleCallbacks;

use Doctrine\ODM\CouchDB\Mapping\Annotations\Document;
use Doctrine\ODM\CouchDB\Mapping\Annotations\EmbedMany;
use Doctrine\ODM\CouchDB\Mapping\Annotations\EmbedOne;
use Doctrine\ODM\CouchDB\Mapping\Annotations\HasLifecycleCallbacks;
use Doctrine\ODM\CouchDB\Mapping\Annotations\Id;

/**
 * @Document()
 * @HasLifecycleCallbacks()
 */
class CallbackUser extends CallbackDocument
{
    /**
     * @Id()
     */
    public $id;

    /**
     * @EmbedOne(targetDocument="CallbackProfile")
     */
    public $profile;

    /**
     * @EmbedMany(targetDocument="CallbackProfile")
     */
    public $profiles = array();

}
