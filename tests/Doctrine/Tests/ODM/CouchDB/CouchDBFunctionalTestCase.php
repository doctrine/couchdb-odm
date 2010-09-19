<?php

namespace Doctrine\Tests\ODM\CouchDB;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    /**
     * @return \Doctrine\ODM\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();
        }
        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }
}