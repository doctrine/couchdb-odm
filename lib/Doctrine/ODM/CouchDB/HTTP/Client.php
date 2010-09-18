<?php
/** HTTP Client interface
 *
 */

namespace Doctrine\ODM\CouchDB\HTTP;

/**
 * Basic couch DB connection handling class
 */
abstract class Client
{
    /**
     * CouchDB connection options
     * 
     * @var array
     */
    protected $options = array(
        'host'       => 'localhost',
        'port'       => 5984,
        'ip'         => '127.0.0.1',
        'timeout'    => .01,
        'keep-alive' => true,
        'username'   => null,
        'password'   => null,
    );

    /**
     * Construct a couch DB connection
     *
     * Construct a couch DB connection from basic connection parameters for one
     * given database.
     *
     * In most cases you want to use the createInstance() method to register
     * the connection instance, so it can be used by the document and view
     * classes. If you want to operate directly on a raw connection you may
     * also instantiate it directly, though.
     *
     * @param string $host
     * @param int $port
     * @return phpillowConnection
     */
    public function __construct( $host, $port, $username = null, $password = null, $ip = null )
    {
        $this->options['host']     = (string) $host;
        $this->options['port']     = (int) $port;
        $this->options['username'] = $username;
        $this->options['password'] = $password;

        if ($ip === null)
        {
            $this->options['ip'] = gethostbyname($this->options['host']);
        }
        else
        {
            $this->options['ip'] = $ip;
        }
    }

    /**
     * Set option value
     *
     * Set the value for an connection option. Throws an
     * phpillowOptionException for unknown options.
     * 
     * @param string $option 
     * @param mixed $value 
     * @return void
     */
    public function setOption( $option, $value )
    {
        switch ( $option )
        {
            case 'keep-alive':
                $this->options[$option] = (bool) $value;
                break;

            case 'http-log':
            case 'password':
            case 'username':
                $this->options[$option] = $value;
                break;

            default:
                throw new phpillowOptionException( $option );
        }
    }

    /**
     * Perform a request to the server and return the result
     *
     * Perform a request to the server and return the result converted into a
     * phpillowResponse object. If you do not expect a JSON structure, which
     * could be converted in such a response object, set the fourth parameter to
     * true, and you get a response object retuerned, containing the raw body.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @param bool $raw
     * @return phpillowResponse
     */
    abstract public function request( $method, $path, $data, $raw = false );
}

