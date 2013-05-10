<?php

namespace Doctrine\Tests\Models\Mapping;

/**
 * @Document
 */
class BaseArticle
{
    /** @Id */
    public $id;
    /** @Field(type="string") */
    public $topic;
}
