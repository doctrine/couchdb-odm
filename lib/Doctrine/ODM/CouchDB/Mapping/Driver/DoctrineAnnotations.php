<?php

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Annotations\Annotation;

final class Document extends Annotation
{
    public $type;
    public $repositoryClass;
    public $indexed = false;
}
final class EmbeddedDocument extends Annotation
{
    public $readOnly = false;
}
final class MappedSuperclass extends Annotation {}

class Field extends Annotation
{
    public $jsonName;
    public $type = 'mixed';
    public $indexed = false;
}
final class Id extends Field
{
    public $id = true;
    public $type = 'string';
    public $strategy = 'UUID';
}
final class Version extends Field
{
    public $type = 'string';
    public $jsonName = '_rev';
}
final class Boolean extends Field
{
    public $type = 'boolean';
}
final class Int extends Field
{
    public $type = 'int';
}
final class Float extends Field
{
    public $type = 'float';
}
final class String extends Field
{
    public $type = 'string';
}
final class Date extends Field
{
    public $type = 'date';
}
final class ArrayField extends Field
{
    public $type = 'array';
}
class Reference extends Annotation
{
    public $targetDocument;
}
final class EmbedOne extends Reference
{
    public $jsonName;
    public $embedded = 'one';
}
final class EmbedMany extends Reference
{
    public $jsonName;
    public $embedded = 'many';
}
final class ReferenceOne extends Reference
{
    public $cascade = array();
}
final class ReferenceMany extends Reference
{
    public $cascade = array();
    public $mappedBy;
}
final class Attachments extends Reference
{
}
