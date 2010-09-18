<?php
/*
 *
 */

namespace Doctrine\ODM\CouchDB\HTTP;

/**
 * Base exception class for package Doctrine\ODM\CouchDB\HTTP
 */
class HTTPException extends \Doctrine\ODM\CouchDB\CouchDBException
{
    public static function connectionFailure( $ip, $port, $errstr, $errno )
    {
        return new static( sprintf(
            "Could not connect to server at %s:%d: '%d: %s'",
            $ip,
            $port,
            $errno,
            $errstr
        ) );
    }
}

