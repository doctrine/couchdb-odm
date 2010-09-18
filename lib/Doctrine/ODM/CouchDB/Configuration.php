<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\Client;

class Configuration
{
    private $options = array();

    public function setHttpClient(Client $client)
    {
        $this->options['httpclient'] = $client;
    }

    public function getHttpClient()
    {
        return $this->options['httpclient'];
    }

    public function getDatabaseName()
    {
        return $this->options['databaseName'];
    }

    public function newDocumentManager()
    {
        return new DocumentManager($this);
    }
}