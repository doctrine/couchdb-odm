<?php

namespace Doctrine\Tests\Models\Embedded;

/**
 * @Document
 */
class Embedder {
    /**
     * @Id(strategy="ASSIGNED")
     */
    public $id;

    /**
     * @Field
     */
    public $name;

    /**
     * @EmbedMany(targetDocument="Doctrine\Tests\Models\Embedded\Embedded")
     */
    public $embeds = array();
    
    /**
     * @EmbedOne(targetDocument="Doctrine\Tests\Models\Embedded\Embedded")
     */
    public $embedded;
}
