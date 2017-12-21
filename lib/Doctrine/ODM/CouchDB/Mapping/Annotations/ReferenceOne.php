<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class ReferenceOne extends Reference
{
    public $cascade = array();
}
