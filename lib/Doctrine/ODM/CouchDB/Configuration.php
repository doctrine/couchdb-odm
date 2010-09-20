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
        if (!isset($this->options['httpclient'])) {
            $this->options['httpclient'] = new HTTP\SocketClient();
        }

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

    public function setProxyDir($directory)
    {
        $this->options['proxydir'] = $directory;
    }

    public function getProxyDir()
    {
        if (!isset($this->options['proxydir'])) {
            $this->options['proxydir'] = \sys_get_temp_dir();
        }

        return $this->options['proxydir'];
    }

    public function newDocumentManager()
    {
        return new DocumentManager($this);
    }
}
