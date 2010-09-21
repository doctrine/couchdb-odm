<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\Client;

class Configuration
{
    /**
     * Array of attributes for this configuration instance.
     *
     * @var array $attributes
     */
    private $attributes = array();

    public function setHttpClient(Client $client)
    {
        $this->attributes['httpclient'] = $client;
    }

    public function getHttpClient()
    {
        if (!isset($this->attributes['httpclient'])) {
            $this->attributes['httpclient'] = new HTTP\SocketClient();
        }

        return $this->attributes['httpclient'];
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param Driver $driverImpl
     * @todo Force parameter to be a Closure to ensure lazy evaluation
     *       (as soon as a metadata cache is in effect, the driver never needs to initialize).
     */
    public function setMetadataDriverImpl(Driver $driverImpl)
    {
        $this->attributes['metadataDriverImpl'] = $driverImpl;
    }

    /**
     * Add a new default annotation driver with a correctly configured annotation reader.
     *
     * @param array $paths
     * @return Mapping\Driver\AnnotationDriver
     */
    public function newDefaultAnnotationDriver($paths = array())
    {
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');

        return new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader, (array) $paths);
    }

    /**
     * Gets the cache driver implementation that is used for the mapping metadata.
     *
     * @return Mapping\Driver\Driver
     */
    public function getMetadataDriverImpl()
    {
        return isset($this->attributes['metadataDriverImpl']) ?
            $this->attributes['metadataDriverImpl'] : null;
    }

    /**
     * Gets the cache driver implementation that is used for metadata caching.
     *
     * @return \Doctrine\Common\Cache\Cache
     */
    public function getMetadataCacheImpl()
    {
        return isset($this->attributes['metadataCacheImpl']) ?
                $this->attributes['metadataCacheImpl'] : null;
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param \Doctrine\Common\Cache\Cache $cacheImpl
     */
    public function setMetadataCacheImpl(Cache $cacheImpl)
    {
        $this->attributes['metadataCacheImpl'] = $cacheImpl;
    }

    /**
     * Sets the directory where Doctrine generates any necessary proxy class files.
     *
     * @param string $dir
     */
    public function setProxyDir($dir)
    {
        $this->attributes['proxyDir'] = $dir;
    }

    /**
     * Gets the directory where Doctrine generates any necessary proxy class files.
     *
     * @return string
     */
    public function getProxyDir()
    {
        if (!isset($this->attributes['proxyDir'])) {
            $this->attributes['proxyDir'] = \sys_get_temp_dir();
        }

        return $this->attributes['proxyDir'];
    }

    /**
     * Gets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @return boolean
     */
    public function getAutoGenerateProxyClasses()
    {
        return isset($this->attributes['autoGenerateProxyClasses']) ?
                $this->attributes['autoGenerateProxyClasses'] : true;
    }

    /**
     * Sets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @param boolean $bool
     */
    public function setAutoGenerateProxyClasses($bool)
    {
        $this->attributes['autoGenerateProxyClasses'] = $bool;
    }

    /**
     * Gets the namespace where proxy classes reside.
     *
     * @return string
     */
    public function getProxyNamespace()
    {
        return isset($this->attributes['proxyNamespace']) ?
                $this->attributes['proxyNamespace'] : null;
    }

    /**
     * Sets the namespace where proxy classes reside.
     *
     * @param string $ns
     */
    public function setProxyNamespace($ns)
    {
        $this->attributes['proxyNamespace'] = $ns;
    }

    /**
     * Sets the default DB to use for all Documents that do not specify
     * a database.
     *
     * @param string $defaultDB
     */
    public function setDefaultDB($defaultDB)
    {
        $this->attributes['defaultDB'] = $defaultDB;
    }

    /**
     * Gets the default DB to use for all Documents that do not specify a database.
     *
     * @return string $defaultDB
     */
    public function getDefaultDB()
    {
        return isset($this->attributes['defaultDB']) ?
            $this->attributes['defaultDB'] : null;
    }

    /**
     * Set the logger callable.
     *
     * @param mixed $loggerCallable The logger callable.
     */
    public function setLoggerCallable($loggerCallable)
    {
        $this->attributes['loggerCallable'] = $loggerCallable;
    }

    /**
     * Gets the logger callable.
     *
     * @return mixed $loggerCallable The logger callable.
     */
    public function getLoggerCallable()
    {
        return isset($this->attributes['loggerCallable']) ?
                $this->attributes['loggerCallable'] : null;
    }

    /**
     * Set prefix for db name
     *
     * @param string $prefix The prefix for names of databases
     */
    public function setDBPrefix($prefix = null)
    {
        $this->attributes['dbPrefix'] = $prefix;
    }

    /**
     * Get prefix for db name
     *
     * @return string
     */
    public function getDBPrefix()
    {
        return isset($this->attributes['dbPrefix']) ?
            $this->attributes['dbPrefix'] : null;
    }
}
