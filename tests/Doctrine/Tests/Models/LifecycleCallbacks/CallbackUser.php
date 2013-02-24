<?php

namespace Doctrine\Tests\Models\LifecycleCallbacks;

/**
 *
 * @Document
 * @HasLifecycleCallbacks
 */
class CallbackUser extends CallbackDocument
{
    /**
     * @Id
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