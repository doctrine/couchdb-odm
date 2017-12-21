<?php

namespace Doctrine\ODM\CouchDB;

/**
 * An OptimisticLockException is thrown when a version check on an object
 * that uses optimistic locking through a version field fails.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since 2.0
 */
class OptimisticLockException extends CouchDBException
{
    private $document;

    public function __construct($msg, $document)
    {
        $this->document = $document;
    }

    /**
     * Gets the document that caused the exception.
     *
     * @return object
     */
    public function getDocument()
    {
        return $this->document;
    }

    public static function lockFailed($document)
    {
        return new self("The optimistic lock on an document failed.", $document);
    }

    public static function lockFailedVersionMissmatch($document, $expectedLockVersion, $actualLockVersion)
    {
        return new self("The optimistic lock failed, version " . $expectedLockVersion . " was expected, but is actually ".$actualLockVersion, $document);
    }

    public static function notVersioned($entityName)
    {
        return new self("Cannot obtain optimistic lock on unversioned document " . $entityName, null);
    }
}
