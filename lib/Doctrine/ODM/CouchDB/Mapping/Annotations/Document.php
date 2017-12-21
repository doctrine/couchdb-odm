<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class Document extends Annotation
{
    public $type;
    public $repositoryClass;
    public $indexed = false;
}
