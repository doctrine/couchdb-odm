<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;

use Doctrine\CouchDB\HTTP\Client;
use Doctrine\CouchDB\HTTP\SocketClient;
use Doctrine\CouchDB\HTTP\LoggingClient;

use Doctrine\ODM\CouchDB\Mapping\MetadataResolver\MetadataResolver;
use Doctrine\ODM\CouchDB\Mapping\MetadataResolver\DoctrineResolver;
use Doctrine\ODM\CouchDB\Migrations\DocumentMigration;

/**
 * Configuration class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class Configuration
{
    /**
     * Array of attributes for this configuration instance.
     *
     * @var array $attributes
     */
    private $attributes = array(
        'designDocuments' => array(
            'doctrine_associations' => array(
                'className' => 'Doctrine\ODM\CouchDB\View\DoctrineAssociations',
                'options' => array(),
            ),
            'doctrine_repositories' => array(
                'className' => 'Doctrine\ODM\CouchDB\View\DoctrineRepository',
                'options' => array(),
            ),
        ),
        'writeDoctrineMetadata' => true,
        'validateDoctrineMetadata' => true,
        'UUIDGenerationBufferSize' => 20,
        'proxyNamespace' => 'MyCouchDBProxyNS',
        'allOrNothingFlush' => true,
        'luceneHandlerName' => false,
        'metadataResolver' => null,
        'autoGenerateProxyClasses' => false,
    );

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
        return $this->attributes['validateDoctrineMetadata'];
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
     * @param array $documentNamespaces
     * @return void
     */
    public function setDocumentNamespaces(array $documentNamespaces)
    {
        $this->attributes['documentNamespaces'] = $documentNamespaces;
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param MappingDriver $driverImpl
     * @todo Force parameter to be a Closure to ensure lazy evaluation
     *       (as soon as a metadata cache is in effect, the driver never needs to initialize).
     */
    public function setMetadataDriverImpl(MappingDriver $driverImpl)
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
        $reader = new \Doctrine\Common\Annotations\SimpleAnnotationReader();
        $reader->addNamespace('Doctrine\ODM\CouchDB\Mapping\Annotations');

        return new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader, (array) $paths);
    }

    public function setMetadataResolverImpl(MetadataResolver $resolver)
    {
        $this->attributes['metadataResolver'] = $resolver;
    }

    public function getMetadataResolverImpl()
    {
        if (!$this->attributes['metadataResolver']) {
            return new DoctrineResolver();
        }
        return $this->attributes['metadataResolver'];
    }

    /**
     * Gets the cache driver implementation that is used for the mapping metadata.
     *
     * @return MappingDriver
     */
    public function getMetadataDriverImpl()
    {
        if (!isset($this->attributes['metadataDriverImpl'])) {
            $this->attributes['metadataDriverImpl'] = $this->newDefaultAnnotationDriver();
        }
        return $this->attributes['metadataDriverImpl'];
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
        return $this->attributes['proxyNamespace'];
    }

    public function setAutoGenerateProxyClasses($bool)
    {
        $this->attributes['autoGenerateProxyClasses'] = $bool;
    }

    public function getAutoGenerateProxyClasses()
    {
        return $this->attributes['autoGenerateProxyClasses'];
    }

    /**
     * @param string $name
     * @param string $className
     * @param array $options
     */
    public function addDesignDocument($name, $className, $options)
    {
        $this->attributes['designDocuments'][$name] = array(
            'className' => $className,
            'options' => $options,
        );
    }

    /**
     * @return array
     */
    public function getDesignDocumentNames()
    {
        return array_keys($this->attributes['designDocuments']);
    }

    /**
     * @param  string $name
     * @return array
     */
    public function getDesignDocument($name)
    {
        if (isset($this->attributes['designDocuments'][$name])) {
            return $this->attributes['designDocuments'][$name];
        }
        return null;
    }

    /**
     * @param bool $allOrNothing
     */
    public function setAllOrNothingFlush($allOrNothing)
    {
        $this->attributes['allOrNothingFlush'] = (bool)$allOrNothing;
    }

    /**
     * @return bool
     */
    public function getAllOrNothingFlush()
    {
        return $this->attributes['allOrNothingFlush'];
    }

    public function setLuceneHandlerName($handlerName = '_fti')
    {
        $this->attributes['luceneHandlerName'] = $handlerName;
    }

    public function getLuceneHandlerName()
    {
        if (!$this->attributes['luceneHandlerName']) {
            throw CouchDBException::luceneNotConfigured();
        }

        return $this->attributes['luceneHandlerName'];
    }

    /**
     * @return \Doctrine\ODM\CouchDB\Migrations\NullMigration;
     */
    public function getMigrations()
    {
        if (!isset($this->attributes['migrations'])) {
            $this->attributes['migrations'] = new Migrations\NullMigration();
        }

        return $this->attributes['migrations'];
    }

    /**
     * @param DocumentMigration $migration
     */
    public function setMigrations(DocumentMigration $migration)
    {
        $this->attributes['migrations'] = $migration;
    }
}
