<?php

namespace Doctrine\Tests\Models\Mapping;

/**
 * Model to test extending another model
 *
 * @Document
 */
class HeadlineArticle extends BaseArticle
{
    /** @Field(type="string") */
    public $headline;
}
