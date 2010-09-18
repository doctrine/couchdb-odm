<?php

namespace Doctrine\ODM\CouchDB\Persisters;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class BasicDocumentPersister
{
    /**
     * Metadata object that describes the mapping of the mapped document class.
     *
     * @var Doctrine\ODM\CouchDB\Mapping\ClassMetadata
     */
    private $class;

    /**
     * The underlying HTTP Connection of the used DocumentManager.
     *
     * @var Doctrine\ODM\CouchDB\HTTP\Client $httpClient
     */
    private $httpClient;

    /**
     * The documentManager instance.
     *
     * @var Doctrine\ODM\CouchDB\DocumentManager
     */
    private $dm = null;

    public function __construct(DocumentManager $dm, ClassMetadata $class)
    {
        $this->dm = $dm;
        $this->class = $class;
        $this->database = $this->dm->getConfiguration()->getDatabaseName();
        // TODO: add a wrapper here that knows the database and makes it easy to construct CouchDB REST requests
        $this->httpClient = $this->dm->getConfiguration()->getHttpClient();
    }

    /**
     * Adds an document to the queued insertions.
     * The document remains queued until {@link executeInserts} is invoked.
     *
     * @param object $document The document to queue for insertion.
     */
    public function addInsert($document)
    {
        //TODO: implement
    }

    /**
     * Executes all queued document insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the document class does not use the IDdocument generation strategy.
     */
    public function executeInserts()
    {
        //TODO: implement
    }

    /**
     * Updates a managed document. The document is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * The data to update is retrieved through {@link _prepareUpdateData}.
     * Subclasses that override this method are supposed to obtain the update data
     * in the same way, through {@link _prepareUpdateData}.
     *
     * Subclasses are also supposed to take care of versioning when overriding this method,
     * if necessary. The {@link _updateTable} method can be used to apply the data retrieved
     * from {@_prepareUpdateData} on the target tables, thereby optionally applying versioning.
     *
     * @param object $document The document to update.
     */
    public function update($document)
    {
        //TODO: implement
    }

    /**
     * Deletes a managed document.
     *
     * The document to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of document deletion.
     *
     * @param object $document The document to delete.
     */
    public function delete($document)
    {
        //TODO: implement
    }

    /**
     * Gets the ClassMetadata instance of the document class this persister is used for.
     *
     * @return Doctrine\ORM\Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * Loads an document by a list of field criteria.
     *
     * @param string $id The criteria by which to load the document.
     * @param object $document The document to load the data into. If not specified,
     *        a new document is created.
     * @param $assoc The association that connects the document to load to another document, if any.
     * @param array $hints Hints for document creation.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     * @todo Check iddocument map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load($id, $document = null, $assoc = null, array $hints = array())
    {
        // TODO: what structure should criteria have? for now assume its a plain CouchDB document id as a string
        $documentPath = '/' . urlencode($this->database) . '/' . urlencode($id);
        $response = $this->httpClient->request( 'GET', $documentPath );
        return $this->createDocument($response, $document, $hints);
    }

    /**
     * Creates or fills a single document object from a result.
     *
     * @param $response The http response.
     * @param object $document The document object to fill, if any.
     * @param array $hints Hints for document creation.
     * @return object The filled and managed document object or NULL, if the result is empty.
     */
    private function createDocument($response, $document = null, array $hints = array())
    {
        if ($response->status > 400) {
            return null;
        }

        list($documentName, $data) = $this->processResponseBody($response->body);

        if ($document !== null) {
            $hints[Query::HINT_REFRESH] = true;
            $id = array();
            if ($this->class->isIdentifierComposite) {
                foreach ($this->class->identifier as $fieldName) {
                    $id[$fieldName] = $data[$fieldName];
                }
            } else {
                $id = array($this->class->identifier[0] => $data[$this->class->identifier[0]]);
            }
            $this->dm->getUnitOfWork()->registerManaged($document, $id, $data);
        }

        return $this->dm->getUnitOfWork()->createDocument($documentName, $data, $hints);
    }

    /**
     * Processes a response body that contains data for an document of the type
     * this persister is responsible for.
     *
     * Subclasses are supposed to override this method if they need to change the
     * hydration procedure for entities loaded through basic find operations or
     * lazy-loading (not DQL).
     *
     * @param array $responseBody The response body to process.
     * @return array A tuple where the first value is the actual type of the document and
     *               the second value the prepared data of the document (a map from field
     *               names to values).
     */
    protected function processResponseBody(array $responseBody)
    {
        $data = array();
        foreach ($responseBody as $resultKey => $value) {
            // TODO: Check how ORM does this? Method or public property?
            if (isset($this->class->resultKeyProperties[$resultKey])) {
                $property = $this->class->resultKeyProperties[$resultKey];
                // TODO: type conversion should probably be handled in the UnitOfWork
                $data[$property] = $value;
            }
        }

        return array($this->class->name, $data);
    }

    /**
     * Loads an document of this persister's mapped class as part of a single-valued
     * association from another document.
     *
     * @param array $assoc The association to load.
     * @param object $sourcedocument The document that owns the association (not necessarily the "owning side").
     * @param object $targetdocument The existing ghost document (proxy) to load, if any.
     * @param array $identifier The identifier of the document to load. Must be provided if
     *                          the association to load represents the owning side, otherwise
     *                          the identifier is derived from the $sourcedocument.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     */
    public function loadOneToOneDocument(array $assoc, $sourcedocument, $targetdocument, array $identifier = array())
    {
        //TODO: implement
    }

    /**
     * Refreshes a managed document.
     *
     * @param array $id The identifier of the document as an associative array from
     *                  column or field names to values.
     * @param object $document The document to refresh.
     */
    public function refresh(array $id, $document)
    {
        //TODO: implement
    }

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array $criteria
     * @return array
     */
    public function loadAll(array $criteria = array())
    {
        //TODO: implement
    }

    /**
     * Checks whether the given managed document exists in the database.
     *
     * @param object $document
     * @return boolean TRUE if the document exists in the database, FALSE otherwise.
     */
    public function exists($document)
    {
        //TODO: implement
    }
}
