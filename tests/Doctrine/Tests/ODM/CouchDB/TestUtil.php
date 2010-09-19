<?php

namespace Doctrine\Tests\ODM\CouchDB;

class TestUtil
{
    static public function getTestDatabase()
    {
        if (isset($_GLOBALS['DOCTRINE_COUCHDB_DATABASE'])) {
            return $_GLOBALS['DOCTRINE_COUCHDB_DATABASE'];
        }
        return 'doctrine_test_database';
    }
}