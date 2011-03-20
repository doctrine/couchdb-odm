<?php

namespace Doctrine\Tests\Models\Embedded;


/**
 * @EmbeddedDocument
 */
class Embedded {
    /**
     * @Field
     */
    public $name;
    
    /**
     * @EmbedMany
     */
    public $embeds = array();
    
    /**
     * @Field
     */
    public $arrayField;
}
