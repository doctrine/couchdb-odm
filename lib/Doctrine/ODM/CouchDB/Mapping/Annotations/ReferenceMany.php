<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class ReferenceMany extends Reference
{
    public $cascade = array();
    public $mappedBy;
}
