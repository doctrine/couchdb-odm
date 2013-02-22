<?php

namespace Doctrine\Tests\Models\LifecycleCallbacks;

/**
 *
 * @EmbeddedDocument
 */
class CallbackProfile extends CallbackDocument
{
    /**
     * @EmbedOne(targetDocument="CallbackProfile")
     */
    public $profile;
}