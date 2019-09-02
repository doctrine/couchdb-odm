<?php

namespace Doctrine\ODM\CouchDB\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
final class EmbedOne extends Reference
{
    public $jsonName;
    public $embedded = 'one';
}
