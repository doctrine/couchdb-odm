<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\Client;
use Doctrine\ODM\CouchDB\Mapping\Driver\Driver;
use Doctrine\Common\Cache\Cache;

class Configuration
{
    /**
     * Array of attributes for this configuration instance.
     *
     * @var array $attributes
     */
    private $attributes = array();

    /**
     * Sets the default UUID Generator buffer size
     *
     * @param integer $UUIDGenerationBufferSize
     */
    public function setUUIDGenerationBufferSize($UUIDGenerationBufferSize)
    {
        $this->attributes['UUIDGenerationBufferSize'] = $UUIDGenerationBufferSize;
    }

    /**
     * Gets the default UUID Generator buffer size
     *
     * @return integer
     */
    public function getUUIDGenerationBufferSize()
    {
        if (!isset($this->attributes['UUIDGenerationBufferSize'])) {
            $this->attributes['UUIDGenerationBufferSize'] = 20;
        }

        return $this->attributes['UUIDGenerationBufferSize'];
    }
    /**
     * Sets if all CouchDB document metadata should be validated on read
     *
     * @param boolean $validateDoctrineMetadata
     */
    public function setValidateDoctrineMetadata($validateDoctrineMetadata)
    {
        $this->attributes['validateDoctrineMetadata'] = $validateDoctrineMetadata;
    }

    /**
     * Gets if all CouchDB document metadata should be validated on read
     *
     * @return boolean
     */
    public function getValidateDoctrineMetadata()
    {
        return !empty($this->attributes['validateDoctrineMetadata']);
    }

    /**
     * Sets if all CouchDB documents should automatically get doctrine metadata added on write
     *
     * @param boolean $writeDoctrineMetadata
     */
    public function setWriteDoctrineMetadata($writeDoctrineMetadata)
    {
        $this->attributes['writeDoctrineMetadata'] = $writeDoctrineMetadata;
    }

    /**
     * Gets if all CouchDB documents should automatically get doctrine metadata added on write
     *
     * @return boolean
     */
    public function getWriteDoctrineMetadata()
    {
        return !empty($this->attributes['writeDoctrineMetadata']);
    }

    /**
     * Sets the HTTP client instance to use for the CouchDB communication
     *
     * @param Client $client
     */
    public function setHttpClient(Client $client)
    {
        $this->attributes['httpclient'] = $client;
    }

    /**
     * Gets the HTTP client instance to use for the CouchDB communication
     *
     * @return Client
     */
    public function getHttpClient()
    {
        if (!isset($this->attributes['httpclient'])) {
            $this->attributes['httpclient'] = new HTTP\SocketClient();
        }

        return $this->attributes['httpclient'];
    }

    /**
     * Adds a namespace under a certain alias.
     *
     * @param string $alias
     * @param string $namespace
     */
    public function addDocumentNamespace($alias, $namespace)
    {
        $this->attributes['documentNamespaces'][$alias] = $namespace;
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * @param string $documentNamespaceAlias
     * @return string
     * @throws CouchDBException
     */
    public function getDocumentNamespace($documentNamespaceAlias)
    {
        if ( ! isset($this->attributes['documentNamespaces'][$documentNamespaceAlias])) {
            throw CouchDBException::unknownDocumentNamespace($documentNamespaceAlias);
        }

        return trim($this->attributes['documentNamespaces'][$documentNamespaceAlias], '\\');
    }

    /**
     * Set the document alias map
     *
     * @param array $documentAliasMap
     * @return void
     */
    public function setDocumentNamespaces(array $documentNamespaces)
    {
        $this->attributes['documentNamespaces'] = $documentNamespaces;
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
     * Sets the namespace for Doctrine proxy class files.
     *
     * @param string $namespace
     */
    public function setProxyNamespace($namespace)
    {
        $this->attributes['proxyNamespace'] = $namespace;
    }

    /**
     * Gets the namespace for Doctrine proxy class files.
     *
     * @return string
     */
    public function getProxyNamespace()
    {
        if (!isset($this->attributes['proxyNamespace'])) {
            $this->attributes['proxyNamespace'] = 'MyCouchDBProxyNS';
        }

        return $this->attributes['proxyNamespace'];
    }

    /**
     * Set the database name
     *
     * @param string $prefix The prefix for names of databases
     */
    public function setDatabase($databaseName)
    {
        $this->attributes['databaseName'] = $databaseName;
    }

    /**
     * Get the database name
     *
     *
     * @return string
     */
    public function getDatabase()
    {
        return isset($this->attributes['databaseName']) ?
            $this->attributes['databaseName'] : null;
    }
}
