<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class Version extends Field
{
    public $type = 'string';
    public $jsonName = '_rev';
}
