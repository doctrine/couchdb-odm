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

use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\View;
use Doctrine\CouchDB\View\Query;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
use Doctrine\ODM\CouchDB\View\ODMQuery;
use Doctrine\ODM\CouchDB\View\ODMLuceneQuery;
use Doctrine\ODM\CouchDB\CouchDBException;

class DocumentManager implements ObjectManager
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork = null;

    /**
     * @var \Doctrine\ODM\CouchDB\Proxy\ProxyFactory
     */
    private $proxyFactory = null;

    /**
     * @var array
     */
    private $repositories = array();

    /**
     * @var CouchDBClient
     */
    private $couchDBClient = null;

    /**
     * @var EventManager
     */
    private $evm;

    /**
     * @param CouchDBClient $couchClient
     * @param Configuration $config
     * @param EventManager $evm
     */
    public function __construct(CouchDBClient $couchClient, Configuration $config = null, EventManager $evm = null)
    {
        $this->couchDBClient = $couchClient;
        $this->config = $config ?: new Configuration();
        $this->evm = $evm ?: new EventManager();
        $this->metadataFactory = new ClassMetadataFactory($this);
        $this->unitOfWork = new UnitOfWork($this);
        $this->proxyFactory = new Proxy\ProxyFactory($this, $this->config->getProxyDir(), $this->config->getProxyNamespace(), $this->config->getAutoGenerateProxyClasses());
    }

    /**
     * @return EventManager
     */
    public function getEventManager()
    {
        return $this->evm;
    }

    /**
     * @return CouchDBClient
     */
    public function getCouchDBClient()
    {
        return $this->couchDBClient;
    }

    /**
     * Factory method for a Document Manager.
     *
     * @param array|CouchDBClient $couchParams
     * @param Configuration       $config
     * @param EventManager        $evm
     * @return DocumentManager
     * @throws \InvalidArgumentException
     */
    public static function create($couchParams, Configuration $config = null, EventManager $evm = null)
    {
        if (is_array($couchParams)) {
            $couchClient = CouchDBClient::create($couchParams);
        } else if ($couchParams instanceof CouchDBClient) {
            $couchClient = $couchParams;
        } else {
            throw new \InvalidArgumentException("Expecting array of instance of CouchDBClient as first argument to DocumentManager::create().");
        }

        return new DocumentManager($couchClient, $config, $evm);
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @return Proxy\ProxyFactory
     */
    public function getProxyFactory()
    {
        return $this->proxyFactory;
    }

    public function getHttpClient()
    {
        return $this->couchDBClient->getHttpClient();
    }

    public function getDatabase()
    {
        return $this->couchDBClient->getDatabase();
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getClassMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @param  string $class
     * @return \Doctrine\ODM\CouchDB\Mapping\ClassMetadata
     */
    public function getClassMetadata($class)
    {
        return $this->metadataFactory->getMetadataFor($class);
    }

    /**
     * Find the Document with the given id.
     *
     * Will return null if the document wasn't found.
     *
     * @param string $documentName
     * @param string $id
     * @return object
     */
    public function find($documentName, $id)
    {
        return $this->getRepository($documentName)->find($id);
    }

    /**
     * @param  string $documentName
     * @return \Doctrine\ODM\CouchDB\DocumentRepository
     */
    public function getRepository($documentName)
    {
        $documentName  = ltrim($documentName, '\\');
        if (!isset($this->repositories[$documentName])) {
            $class = $this->getClassMetadata($documentName);
            if ($class->customRepositoryClassName) {
                $repositoryClass = $class->customRepositoryClassName;
            } else {
                $repositoryClass = 'Doctrine\ODM\CouchDB\DocumentRepository';
            }
            $this->repositories[$documentName] = new $repositoryClass($this, $class);
        }
        return $this->repositories[$documentName];
    }

    /**
     * Create a Query for the view in the specified design document.
     *
     * @param  string $designDocName
     * @param  string $viewName
     * @return \Doctrine\ODM\CouchDB\View\ODMQuery
     */
    public function createQuery($designDocName, $viewName)
    {
        $designDoc = $this->config->getDesignDocument($designDocName);
        if ($designDoc) {
            $designDoc = new $designDoc['className']($designDoc['options']);
        }
        $query = new ODMQuery($this->couchDBClient->getHttpClient(), $this->couchDBClient->getDatabase(), $designDocName, $viewName, $designDoc);
        $query->setDocumentManager($this);
        return $query;
    }

    /**
     * Create a Native query for the view of the specified design document.
     *
     * A native query will return an array of data from the &include_docs=true parameter.
     *
     * @param  string $designDocName
     * @param  string $viewName
     * @return \Doctrine\CouchDB\View\Query
     */
    public function createNativeQuery($designDocName, $viewName)
    {
        $designDoc = $this->config->getDesignDocument($designDocName);
        if ($designDoc) {
            $designDoc = new $designDoc['className']($designDoc['options']);
        }
        $query = new Query($this->couchDBClient->getHttpClient(), $this->couchDBClient->getDatabase(), $designDocName, $viewName, $designDoc);
        return $query;
    }

    /**
     * Create a CouchDB-Lucene Query.
     *
     * @param string $designDocName
     * @param string $viewName
     * @return \Doctrine\ODM\CouchDB\View\ODMLuceneQuery
     */
    public function createLuceneQuery($designDocName, $viewName)
    {
        $luceneHandlerName = $this->config->getLuceneHandlerName();
        $designDoc = $this->config->getDesignDocument($designDocName);
        if ($designDoc) {
            $designDoc = new $designDoc['className']($designDoc['options']);
        }
        $query = new ODMLuceneQuery($this->couchDBClient->getHttpClient(),
            $this->couchDBClient->getDatabase(), $luceneHandlerName, $designDocName,
            $viewName, $designDoc
        );
        $query->setDocumentManager($this);
        return $query;
    }

    public function persist($object)
    {
        $this->unitOfWork->scheduleInsert($object);
    }

    public function remove($object)
    {
        $this->unitOfWork->scheduleRemove($object);
    }

    /**
     * Refresh the given document by querying the CouchDB to get the current state.
     *
     * @param object $document
     */
    public function refresh($document)
    {
        $this->getUnitOfWork()->refresh($document);
    }

    public function merge($document)
    {
        return $this->getUnitOfWork()->merge($document);
    }

    public function detach($document)
    {
        $this->getUnitOfWork()->detach($document);
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $documentName The name of the entity type.
     * @param mixed $identifier The entity identifier.
     * @return object The entity reference.
     */
    public function getReference($documentName, $identifier)
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($documentName, '\\'));

        // Check identity map first, if its already in there just return it.
        if ($document = $this->unitOfWork->tryGetById($identifier)) {
            return $document;
        }
        $document = $this->proxyFactory->getProxy($class->name, $identifier);
        $this->unitOfWork->registerManaged($document, $identifier, null);

        return $document;
    }

    public function flush()
    {
        $this->unitOfWork->flush(); // todo: rename commit
    }

    /**
     * @param  object $document
     * @return bool
     */
    public function contains($document)
    {
        return $this->unitOfWork->contains($document);
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * Clears the ObjectManager. All objects that are currently managed
     * by this ObjectManager become detached.
     *
     * @param string $objectName if given, only objects of this type will get detached
     * @throws CouchDBException
     */
    public function clear($objectName = null)
    {
        if ($objectName === null) {
            // Todo: Do a real delegated clear?
            $this->unitOfWork = new UnitOfWork($this);
        } else {
            //TODO
            throw new CouchDBException("DocumentManager#clear(\$objectName) not yet implemented.");
        }
    }

    /**
     * Initialize an object that is a lazy load proxy, or do nothing.
     *
     * @param object $obj
     */
    public function initializeObject($obj)
    {
        if ($obj instanceof PersistentCollection) {
            $obj->initialize();
        } else if ($obj instanceof Proxy\Proxy) {
            $obj->__doctrineLoad__();
        }
    }
}
