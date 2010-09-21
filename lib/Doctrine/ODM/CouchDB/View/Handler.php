<?php

namespace Doctrine\ODM\CouchDB\View;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\HTTP\ErrorResponse;

/**
 * Basic view handler
 *
 * @TODO: Error handling should be improved and the code should be more 
 * passive.
 */
abstract class Handler
{
    /**
     * @var Doctrine\ODM\CouchDB\DocumentManager
     */
    protected $documentManager;

    /**
     * Name of the view
     * 
     * @var string
     */
    protected $name;

    /**
     * Construct from documenty manager
     * 
     * @param Doctrine\ODM\CouchDB\DocumentManager $documentManager 
     * @return void
     */
    public function __construct(DocumentManager $documentManager, $name)
    {
        $this->documentManager = $documentManager;
        $this->name = $name;
    }

    /**
     * Get view result
     * 
     * @return array
     */
    protected function getViewResult( $name, array $parameters = array() )
    {
        $handle = $this->documentManager->getConfiguration()->getHttpClient();

        $response = $handle->request(
            "GET",
            $path = sprintf(
                "/%s/_design/%s/_view/%s?%s",
                $this->documentManager->getConfiguration()->getDBPrefix().$this->documentManager->getConfiguration()->getDefaultDB(),
                $this->name,
                $name,
                http_build_query( array_map( "json_encode", $parameters ) )
            )
        );

        if ( $response instanceof ErrorResponse ) {
            // Create view, if it does not exist yet
            $this->createView();
            $response = $handle->request( "GET", $path );
        }

        return array_map(
            function ( $value ) {
                return $value['value'];
            },
            $response->body['rows']
        );
    }

    /**
     * Create non existing view
     * 
     * @return void
     */
    public function createView()
    {
        $handle = $this->documentManager->getConfiguration()->getHttpClient();
        $handle->request(
            "PUT",
            sprintf(
                "/%s/_design/%s",
                $this->documentManager->getConfiguration()->getDBPrefix().$this->documentManager->getConfiguration()->getDefaultDB(),
                $this->name
            ),
            json_encode( array(
                '_id'   => '_design/' . $this->name,
                'views' => $this->getViews(),
            ) )
        );
    }

    /**
     * Get view code
     *
     * Return the view code, which should be comitted to the database, which 
     * should be structured like:
     *
     * <code>
     *  array(
     *      "name" => array(
     *          "map"     => "code",
     *          ["reduce" => "code"],
     *      ),
     *      ...
     *  )
     * </code>
     */
    abstract protected function getViews(); 
}
