<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class Id extends Field
{
    public $id = true;
    public $type = 'string';
    public $strategy = 'UUID';
}
