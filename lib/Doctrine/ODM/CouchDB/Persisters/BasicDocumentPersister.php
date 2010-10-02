<?php

namespace Doctrine\ODM\CouchDB\Persisters;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class BasicDocumentPersister
{
    /**
     * Name of the CouchDB database
     *
     * @string
     */
    private $databaseName;

    /**
     * The underlying HTTP Connection of the used DocumentManager.
     *
     * @var Doctrine\ODM\CouchDB\HTTP\Client
     */
    private $httpClient;

    /**
     * The documentManager instance.
     *
     * @var Doctrine\ODM\CouchDB\DocumentManager
     */
    private $dm = null;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        // TODO how to handle the case when the database name changes?
        $this->databaseName = $dm->getConfiguration()->getDatabase();
        $this->httpClient = $dm->getConfiguration()->getHttpClient();
    }

    public function getUuids($count = 1)
    {
        $count = (int)$count;
        $response = $this->httpClient->request('GET', '/_uuids?count=' . $count);

        if ($response->status != 200) {
            throw new \Doctrine\ODM\CouchDB\CouchDBException("Could not retrieve UUIDs from CouchDB.");
        }

        return $response->body['uuids'];
    }

    /**
     * @param  string $id
     * @return Response
     */
    public function findDocument($id)
    {
        $documentPath = '/' . $this->databaseName . '/' . urlencode($id);
        $response =  $this->httpClient->request( 'GET', $documentPath );

        if ($response->status == 404) {
            throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException($id);
        }
        return $response;
    }

    /**
     * Loads an document by a list of field criteria.
     *
     * @param array $query The criteria by which to load the document.
     * @param object $document The document to load the data into. If not specified,
     *        a new document is created.
     * @param $assoc The association that connects the document to load to another document, if any.
     * @param array $hints Hints for document creation.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     * @todo Check iddocument map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(array $query, $document = null, $assoc = null, array $hints = array())
    {
        try {
            // TODO define a proper query array structure
            // view support with view parameters and couchdb parameters (include_docs, limit, sort direction)
            $response = $this->findDocument($query['id']);
            if ($response->status > 400) {
                return null;
            }

            if ($document) {
                $hints['refresh'] = true;
            }

            return $this->dm->getUnitOfWork()->createDocument($query['documentName'], $response->body, $hints);
        } catch(\Doctrine\ODM\CouchDB\DocumentNotFoundException $e) {
            return null;
        }
    }

    /**
     * Load many documents of a type by mass fetching them from the _all_docs document.
     *
     * @param  string $documentName
     * @param  array $ids
     * @return array
     */
    public function loadMany($documentName, $ids)
    {
        $allDocsPath = '/' . $this->databaseName . '/_all_docs?include_docs=true';
        $response = $this->httpClient->request('POST', $allDocsPath, json_encode(array('keys' => $ids)));

        if ($response->status != 200) {
            throw new \Exception("loadMany error code " . $response->status);
        }

        $uow = $this->dm->getUnitOfWork();

        $docs = array();
        if ($response->body['total_rows'] > 0) {
            foreach ($response->body['rows'] AS $responseData) {
                $docs[] = $uow->createDocument($documentName, $responseData['doc']);
            }
        }
        return $docs;
    }
}
