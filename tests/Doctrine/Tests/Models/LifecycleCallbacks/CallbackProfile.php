<?php

namespace Doctrine\Tests\Models\LifecycleCallbacks;

use Doctrine\ODM\CouchDB\Mapping\Annotations\EmbeddedDocument;
use Doctrine\ODM\CouchDB\Mapping\Annotations\EmbedOne;

/**
 * @EmbeddedDocument()
 */
class CallbackProfile extends CallbackDocument
{
    /**
     * @EmbedOne(targetDocument="CallbackProfile")
     */
    public $profile;
}
