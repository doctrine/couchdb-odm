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
        return new self( sprintf(
            "Could not connect to server at %s:%d: '%d: %s'",
            $ip,
            $port,
            $errno,
            $errstr
        ) );
    }

    /**
     * @param Response $response
     */
    public static function fromResponse($path, Response $response)
    {
        return new self("HTTP Error with status " . $response->status . " occoured while ".
            "requesting " . $path . ". Error: " . $response->body['error'] . " " . $response->body['reason']);
    }
}

