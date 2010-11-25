<?php

namespace Doctrine\ODM\CouchDB\View;

use Doctrine\ODM\CouchDB\HTTP\Client;
use Doctrine\ODM\CouchDB\HTTP\ErrorResponse;

abstract class AbstractQuery
{
    /**
     * @var DesignDocument
     */
    protected $doc;

    /**
     * @var string
     */
    protected $designDocumentName;

    /**
     * @var string
     */
    protected $viewName;

    /**
     * @var string
     */
    protected $databaseName;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $params = array();

    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var bool
     */
    private $onlyDocs = false;

    /**
     * @param Client $client
     * @param string $databaseName
     * @param string $viewName
     * @param DesignDocument $doc
     */
    public function __construct(Client $client, $databaseName, $designDocName, $viewName, DesignDocument $doc = null)
    {
        $this->client = $client;
        $this->databaseName = $databaseName;
        $this->designDocumentName = $designDocName;
        $this->viewName = $viewName;
        $this->doc = $doc;
    }

    /**
     * @param DocumentManager $dm
     */
    public function setDocumentManager(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    /**
     * @param  bool $flag
     * @return Query
     */
    public function onlyDocs($flag)
    {
        $this->setIncludeDocs(true);
        $this->onlyDocs = $flag;
        return $this;
    }

    /**
     * Automatically fetch and include the document which emitted each view entry
     *
     * @param  bool $flag
     * @return Query
     */
    public function setIncludeDocs($flag)
    {
        $this->params['include_docs'] = $flag;
        return $this;
    }

    /**
     * @param  string $key
     * @return mixed
     */
    public function getParameter($key)
    {
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }
        return null;
    }

    abstract protected function getHttpQuery();

    /**
     * Query the view with the current params.
     *
     * @return array
     */
    public function execute()
    {
        $path = $this->getHttpQuery();
        $response = $this->client->request("GET", $path);

        if ( $response instanceof ErrorResponse ) {
            // Create view, if it does not exist yet
            $this->createDesignDocument();
            $response = $this->client->request( "GET", $path );
        }


        if ($response->status >= 400) {
            throw new \Exception("Error [" . $response->status . "]: " . $response->body['error'] . " " . $response->body['reason']);
        }

        if ($this->dm && $this->getParameter('include_docs') === true) {
            $uow = $this->dm->getUnitOfWork();
            foreach ($response->body['rows'] AS $k => $v) {
                $doc = $uow->createDocument($v['doc']['doctrine_metadata']['type'], $v['doc']);
                if ($this->onlyDocs) {
                    $response->body['rows'][$k] = $doc;
                } else {
                    $response->body['rows'][$k]['doc'] = $doc;
                }
            }
        }

        return $this->createResult($response);
    }

    /**
     * @return Result
     */
    abstract protected function createResult($response);

    /**
     * Create non existing view
     *
     * @return void
     */
    private function createDesignDocument()
    {
        if (!$this->doc) {
            throw new \Exception("No DesignDocument Class is connected to this view query, cannot create the design document with its corresponding view automatically!");
        }

        $data = $this->doc->getData();
        $data['_id'] = '_design/' . $this->designDocumentName;

        $response = $this->client->request(
            "PUT",
            sprintf(
                "/%s/_design/%s",
                $this->databaseName,
                $this->designDocumentName
            ),
            json_encode($data)
        );
    }
}