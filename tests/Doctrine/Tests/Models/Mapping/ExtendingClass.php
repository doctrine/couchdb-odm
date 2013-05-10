<?php

namespace Doctrine\Tests\Models\Mapping;

/**
 * Model to test extending another model
 *
 * @Document
 */
class ExtendingClass extends MappedSuperclass
{
    /** @Field(type="string") */
    public $headline;
}
