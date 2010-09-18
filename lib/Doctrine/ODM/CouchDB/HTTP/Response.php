<?php
/** HTTP Client interface
 *
 */

namespace Doctrine\ODM\CouchDB\HTTP;

/**
 * HTTP response
 */
class Response
{
    /**
     * HTTP repsonse status
     * 
     * @var int
     */
    public $status;

    /**
     * HTTP repsonse headers
     * 
     * @var array
     */
    public $headers;

    /**
     * Decoded JSON response body
     * 
     * @var array
     */
    public $body;

    /**
     * Construct response
     * 
     * @param array $headers 
     * @param string $body 
     * @return void
     */
    public function __construct( $status, array $headers, $body )
    {
        $this->status  = (int) $status;
        $this->headers = $headers;
        $this->body    = json_decode( $body, true );
    }
}

