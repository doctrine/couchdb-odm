<?php

namespace Doctrine\ODM\CouchDB\View;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\HTTP\ErrorResponse;
use Doctrine\ODM\CouchDB\HTTP\Client;

/**
 * Abstract Design Document
 *
 */
interface DesignDocument
{
    /**
     * Get view code
     *
     * Return the view code, which should be comitted to the database, which 
     * should be structured like:
     *
     * <code>
     *  array(
     *      "name" => array(
     *          "map"     => "code",
     *          ["reduce" => "code"],
     *      ),
     *      ...
     *  )
     * </code>
     */
    public function getViews();
}
