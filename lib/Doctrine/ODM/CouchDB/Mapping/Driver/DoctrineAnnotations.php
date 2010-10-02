<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Annotations\Annotation;

final class Document extends Annotation
{
    public $type;
    public $repositoryClass;
}
final class EmbeddedDocument extends Annotation {}
final class MappedSuperclass extends Annotation {}

class Field extends Annotation
{
    public $jsonName;
    public $type = 'string';
}
final class Id extends Field
{
    public $id = true;
    public $type = 'id';
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
class Reference extends Annotation
{
    public $jsonName;
    public $targetDocument;
}
final class EmbedOne extends Reference
{
}
final class EmbedMany extends Reference
{
}
final class ReferenceOne extends Reference
{
    public $cascade;
}
final class ReferenceMany extends Reference
{
    public $cascade;
}
final class Attachments extends Field
{
    
}
final class Attachment extends Field
{
    public $name;
}