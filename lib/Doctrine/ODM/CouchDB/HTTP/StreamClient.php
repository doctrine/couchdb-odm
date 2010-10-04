<?php
/** HTTP Client interface
 *
 */

namespace Doctrine\ODM\CouchDB\HTTP;

/**
 * Connection handler using PHPs stream wrappers.
 *
 * Requires PHP being compiled with --with-curlwrappers for now, since the PHPs 
 * own HTTP implementation is somehow b0rked.
 */
class StreamClient extends Client
{
    /**
     * Perform a request to the server and return the result
     *
     * Perform a request to the server and return the result converted into a
     * Response object. If you do not expect a JSON structure, which
     * could be converted in such a response object, set the forth parameter to
     * true, and you get a response object retuerned, containing the raw body.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @return Response
     */
    public function request( $method, $path, $data = null, $raw = false )
    {
        $basicAuth = '';
        if ( $this->options['username'] ) {
            $basicAuth .= "{$this->options['username']}:{$this->options['password']}@";
        }

        // TODO SSL support?
        $httpFilePointer = @fopen(
            'http://' . $basicAuth . $this->options['host']  . ':' . $this->options['port'] . $path,
            'r',
            false,
            stream_context_create(
                array(
                    'http' => array(
                        'method'        => $method,
                        'content'       => $data,
                        'ignore_errors' => true,
                        'max_redirects' => 0,
                        'user_agent'    => 'Doctrine CouchDB ODM $Revision$',
                        'timeout'       => $this->options['timeout'],
                        'header'        => 'Content-type: application/json',
                    ),
                )
            )
        );

        // Check if connection has been established successfully
        if ( $httpFilePointer === false ) {
            $error = error_get_last();
            throw HTTPException::connectionFailure(
                $this->options['ip'],
                $this->options['port'],
                $error['message'],
                0
            );
        }

        // Read request body
        $body = '';
        while ( !feof( $httpFilePointer ) ) {
            $body .= fgets( $httpFilePointer );
        }
        
        $metaData = stream_get_meta_data( $httpFilePointer );
        // The structure of this array differs depending on PHP compiled with 
        // --enable-curlwrappers or not. Both cases are normally required.
        $rawHeaders = isset( $metaData['wrapper_data']['headers'] )
            ? $metaData['wrapper_data']['headers'] : $metaData['wrapper_data'];

        $headers = array();
        foreach ( $rawHeaders as $lineContent ) {
            // Extract header values
            if ( preg_match( '(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $lineContent, $match ) ) {
                $headers['version'] = $match['version'];
                $headers['status']  = (int) $match['status'];
            } else {
                list( $key, $value ) = explode( ':', $lineContent, 2 );
                $headers[strtolower( $key )] = ltrim( $value );
            }
        }

        // Create response object from couch db response
        if ( $raw ) {
            return new RawResponse( $headers['status'], $headers, $body );
        } elseif ( $headers['status'] >= 400 ) {
            return new ErrorResponse( $headers['status'], $headers, $body );
        }

        return new Response( $headers['status'], $headers, $body );
    }
}

