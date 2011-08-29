<?php

namespace Doctrine\ODM\CouchDB\Mapping;

/**
 * Mapping exception class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class MappingException extends \Exception
{
    public static function classNotFound($className)
    {
        return new self('The class: ' . $className . ' could not be found');
    }

    public static function classIsNotAValidDocument($className)
    {
        return new self('Class '.$className.' is not a valid document or mapped super class.');
    }

    public static function classSpecifiesMultipleDocumentTypes($className)
    {
        return new self('Class '.$className.' may only contain one Document related class annotation.');
    }

    public static function reflectionFailure($document, \ReflectionException $previousException)
    {
        return new self('An error occurred in ' . $document, 0, $previousException);
    }

    public static function fileMappingDriversRequireConfiguredDirectoryPath()
    {
        return new self('File mapping drivers must have a valid directory path, however the given path seems to be incorrect!');
    }

    public static function classNotMapped()
    {
        return new self();
    }

    public static function noTypeSpecified()
    {
        return new self();
    }

    public static function duplicateFieldMapping($className, $fieldName)
    {
        return new self("Cannot map duplicate field '" . $className . "::" . $fieldName . "'.");
    }

    public static function mappingNotFound($className, $fieldName)
    {
        return new self("No mapping found for field '$fieldName' in class '$className'.");
    }
}
