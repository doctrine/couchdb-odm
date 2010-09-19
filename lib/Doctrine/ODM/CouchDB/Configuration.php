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
        // TODO: automatically set a client if non is set
        return $this->options['httpclient'];
    }

    public function getDatabaseName()
    {
        return $this->options['databaseName'];
    }

    public function setDatabaseName($name)
    {
        $this->options['databaseName'] = $name;
    }

    public function newDocumentManager()
    {
        return new DocumentManager($this);
    }
}
