<?php
/** HTTP Client interface
 *
 */

namespace Doctrine\ODM\CouchDB\HTTP;

/**
 * Connection handler using PHPs stream wrappers.
 */
class SocketClient extends Client
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
    public function request( $method, $path, $data, $raw = false )
    {
        $basicAuth = '';
        if ( $this->options['username'] )
        {
            $basicAuth .= "{$this->options['username']}:{$this->options['password']}@";
        }

        $url = 'http://' . $basicAuth . $this->options['host']  . ':' . $this->options['port'] . $path;

        $httpFilePointer = @fopen(
            $url = 'http://' . $this->options['host']  . ':' . $this->options['port'] . $path, 'r', false,
            stream_context_create(
                array(
                    'http' => array(
                        'method'        => $method,
                        'content'       => $data,
                        'ignore_errors' => true,
                        'user_agent'    => 'PHPillow $Revision$',
                        'timeout'       => $this->options['timeout'],
                        'header'        => 'Content-type: application/json',
                    ),
                )
            )
        );

        // Check if connection has been established successfully
        if ( $httpFilePointer === false )
        {
            $error = error_get_last();
            throw new ConnectionException(
                "Could not connect to server at %ip:%port: %error",
                array(
                    'ip'    => $this->options['ip'],
                    'port'  => $this->options['port'],
                    'error' => $error['message'],
                )
            );
        }

        // Read request body
        $body = '';
        while ( !feof( $httpFilePointer ) )
        {
            $body .= fgets( $httpFilePointer );
        }
        
        $metaData   = stream_get_meta_data( $httpFilePointer );
        // The structure of this array differs depending on PHP compiled with 
        // --enable-curlwrappers or not. Both cases are normally required.
        $rawHeaders = isset( $metaData['wrapper_data']['headers'] ) ? $metaData['wrapper_data']['headers'] : $metaData['wrapper_data'];
        $headers    = array();

        foreach ( $rawHeaders as $lineContent )
        {
            // Extract header values
            if ( preg_match( '(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $lineContent, $match ) )
            {
                $headers['version'] = $match['version'];
                $headers['status']  = (int) $match['status'];
            }
            else
            {
                list( $key, $value ) = explode( ':', $lineContent, 2 );
                $headers[strtolower( $key )] = ltrim( $value );
            }
        }

        // If requested log response information to http log
        if ( $this->options['http-log'] !== false )
        {
            file_put_contents( $this->options['http-log'],
                sprintf( "Requested: %s\n\n%s\n\n%s\n\n",
                    $url,
                    implode( "\n", $rawHeaders ),
                    $body
                )
            );
        }

        // Create repsonse object from couch db response
        return new Response( $headers, $body, $raw );
    }
}

