<?php

namespace Doctrine\ODM\CouchDB;

/**
 * Base exception class for package Doctrine\ODM\CouchDB
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class CouchDBException extends \Exception
{

    public static function unknownDocumentNamespace($documentNamespaceAlias)
    {
        return new self("Unknown Document namespace alias '$documentNamespaceAlias'.");
    }

    public static function unregisteredDesignDocument($designDocumentName)
    {
        return new self("No design document with name '" . $designDocumentName . "' was registered with the DocumentManager.");
    }

    public static function invalidAttachment($className, $id, $filename)
    {
        return new self("Trying to save invalid attachment with filename " . $filename . " in document " . $className . " with id " . $id);
    }

    public static function detachedDocumentFound($className, $id, $assocName)
    {
        return new self("Found a detached or new document at property " .
            $className . "::" . $assocName. " of document with ID " . $id . ", ".
            "but the assocation is not marked as cascade persist.");
    }

    public static function persistRemovedDocument()
    {
        return new self("Trying to persist document that is scheduled for removal.");
    }

    public static function luceneNotConfigured()
    {
        return  new self("CouchDB Lucene is not configured. You have to configure the handler name to enable support for Lucene Queries.");
    }

    public static function assignedIdGeneratorNoIdFound($className)
    {
        return new self("Document $className has assigned id generator configured, " .
            "however no ID was found during persist().");
    }

    public static function unexpectedDocumentType($document)
    {
        $type = gettype($document);
        return new self("Document was expected to be an object. $type given instead.");
    }
}
