<?php

namespace Doctrine\Tests\Models\Mapping;

/**
 * @MappedSuperclass
 */
class MappedSuperclass
{
    /** @Id */
    public $id;
    /** @Field(type="string") */
    public $topic;
}
