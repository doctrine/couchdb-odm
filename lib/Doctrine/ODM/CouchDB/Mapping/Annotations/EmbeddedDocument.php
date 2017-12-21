<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class EmbeddedDocument extends Annotation
{
    public $readOnly = false;
}
