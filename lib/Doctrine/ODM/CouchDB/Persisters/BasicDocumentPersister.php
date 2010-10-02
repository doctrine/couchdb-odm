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

            return $this->createDocument($query['documentName'], $response->body, $document, $hints);
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

        $docs = array();
        if ($response->body['total_rows'] > 0) {
            foreach ($response->body['rows'] AS $responseData) {
                $docs[] = $this->createDocument($documentName, $responseData['doc']);
            }
        }
        return $docs;
    }

    private function getDoctrineMetadata($documentName)
    {
        return array('type' => $documentName);
    }

    /**
     * Creates or fills a single document object from a result.
     *
     * @param string $documentName
     * @param array $responseData The http response.
     * @param object $document The document object to fill, if any.
     * @param array $hints Hints for document creation.
     * @return object The filled and managed document object or NULL, if the result is empty.
     */
    private function createDocument($documentName, $responseData, $document = null, array $hints = array())
    {
        // TODO implement handling for $document

        if (isset($responseData['doctrine_metadata']['type'])) {
            $type = $responseData['doctrine_metadata']['type'];
            if (isset($documentName) && $this->dm->getConfiguration()->getValidateDoctrineMetadata()) {
                $validate = true;
            }
        } else if (isset($documentName)) {
            $type = $documentName;
            if ($this->dm->getConfiguration()->getWriteDoctrineMetadata()) {
                $responseData['doctrine_metadata'] = $this->getDoctrineMetadata($documentName);
            }
        } else {
            throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }

        $class = $this->dm->getClassMetadata($type);

        $data = array();
        foreach ($responseData as $jsonName => $value) {
            // TODO: For migrations and stuff, maybe there should really be a "rest" field?
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    $data[$class->fieldMappings[$fieldName]['fieldName']] = $value;
                } else if (isset($class->associationsMappings[$fieldName])) {

                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if ($value) {
                            $value = $this->dm->getReference($class->associationsMappings[$fieldName]['targetDocument'], $value);
                        }
                        $data[$class->associationsMappings[$fieldName]['fieldName']] = $value;
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::MANY_TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // 1. if owning side we know all the ids
                            $data[$class->associationsMappings[$fieldName]['fieldName']] = new \Doctrine\ODM\CouchDB\PersistentIdsCollection(
                                new \Doctrine\Common\Collections\ArrayCollection(),
                                $class->associationsMappings[$fieldName]['targetDocument'],
                                $this->dm,
                                $value
                            );
                        } else {
                            // 2. if inverse side we need to nest the lazy loading relations view
                            // TODO implement inverse side lazy loading
                        }
                    }
                }
            }
        }

        $hints['refresh'] = true;

        $newdocument = $this->dm->getUnitOfWork()->createDocument($class->name, $data, $responseData["_id"], $responseData["_rev"], $hints);
        if (isset($validate) && !($newdocument instanceof $documentName)) {
            throw new \InvalidArgumentException("Doctrine metadata mismatch! Requested type '$documentName' type does not match type '$type' stored in the metdata");
        }

        return $newdocument;
    }
}
