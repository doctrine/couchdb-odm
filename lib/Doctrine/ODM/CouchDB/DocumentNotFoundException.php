<?php

namespace Doctrine\ODM\CouchDB;

/**
 * Exception thrown when a Proxy fails to retrieve a Document.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Nils Adermann <naderman@naderman.de>
 */
class DocumentNotFoundException extends CouchDBException
{
    public function __construct()
    {
        parent::__construct('Document was not found.');
    }
}
