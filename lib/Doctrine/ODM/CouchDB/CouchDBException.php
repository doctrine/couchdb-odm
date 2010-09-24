<?php
/*
 *
 */

namespace Doctrine\ODM\CouchDB;

/**
 * Base exception class for package Doctrine\ODM\CouchDB
 */
class CouchDBException extends \Exception {
    public static function unknownDocumentNamespace($documentNamespaceAlias)
    {
        return new self("Unknown Document namespace alias '$documentNamespaceAlias'.");
    }
}

