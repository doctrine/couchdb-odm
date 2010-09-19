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
        if (!isset($this->options['httpclient'])) {
            $this->options['httpclient'] = new HTTP\SocketClient();
        }

        return new DocumentManager($this);
    }
}
