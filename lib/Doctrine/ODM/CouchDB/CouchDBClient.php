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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\Client;
use Doctrine\ODM\CouchDB\HTTP\HTTPException;
use Doctrine\ODM\CouchDB\Utils\BulkUpdater;
use Doctrine\ODM\CouchDB\View\DesignDocument;

/**
 * CouchDB client class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class CouchDBClient
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
     * CouchDB Version
     *
     * @var string
     */
    private $version = null;

    /**
     * @param Client $client
     * @param string $databaseName
     */
    public function __construct(Client $client, $databaseName)
    {
        $this->httpClient = $client;
        $this->databaseName = $databaseName;
    }

    /**
     * Let CouchDB generate an array of UUIDs.
     *
     * @param  int $count
     * @return array
     */
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
     * Find a document by ID and return the HTTP response.
     *
     * @param  string $id
     * @return Response
     */
    public function findDocument($id)
    {
        $documentPath = '/' . $this->databaseName . '/' . urlencode($id);
        return $this->httpClient->request( 'GET', $documentPath );
    }

    /**
     * Find many documents by passing their ids and return the HTTP response.
     *
     * @param array $ids
     * @return array
     */
    public function findDocuments(array $ids)
    {
        $allDocsPath = '/' . $this->databaseName . '/_all_docs?include_docs=true';
        return $this->httpClient->request('POST', $allDocsPath, json_encode(array('keys' => $ids)));
    }

    /**
     * Get the current version of CouchDB.
     *
     * @throws HTTPException
     * @return string
     */
    public function getVersion()
    {
        if ($this->version === null) {
            $response = $this->httpClient->request('GET', '/');
            if ($response->status != 200) {
                throw HTTPException::fromResponse('/', $response);
            }

            $this->version = $response->body['version'];
        }
        return $this->version;
    }

    /**
     * Get all databases
     *
     * @throws HTTPException
     * @return array
     */
    public function getAllDatabases()
    {
        $response = $this->httpClient->request('GET', '/_all_dbs');
        if ($response->status != 200) {
            throw HTTPException::fromResponse('/_all_dbs', $response);
        }

        return $response->body;
    }

    /**
     * Create a new database
     *
     * @throws HTTPException
     * @param string $name
     * @return void
     */
    public function createDatabase($name)
    {
        $response = $this->httpClient->request('PUT', '/' . urlencode($name));

        if ($response->status != 201) {
            throw HTTPException::fromResponse('/' . urlencode($name), $response);
        }
    }

    /**
     * Drop a database
     *
     * @throws HTTPException
     * @param string $name
     * @return void
     */
    public function deleteDatabase($name)
    {
        $response = $this->httpClient->request('DELETE', '/' . urlencode($name));

        if ($response->status != 200 && $response->status != 404) {
            throw HTTPException::fromResponse('/' . urlencode($name), $response);
        }
    }

    /**
     * Get Information about a database.
     *
     * @param  string $name
     * @return array
     */
    public function getDatabaseInfo($name)
    {
        $response = $this->httpClient->request('GET', '/' . $this->databaseName);

        if ($response->status != 200) {
            throw HTTPException::fromResponse('/' . urlencode($name), $response);
        }

        return $response->body;
    }

    /**
     * Get changes.
     *
     * @param  array $params
     * @return array
     */
    public function getChanges(array $params = null)
    {
        $path = '/' . $this->databaseName . '/_changes';
        $response = $this->httpClient->request('GET', $path, $params);

        if ($response->status != 200) {
            throw HTTPException::fromResponse($path, $response);
        }

        return $response->body;
    }

    /**
     * Create a bulk updater instance.
     *
     * @return BulkUpdater
     */
    public function createBulkUpdater()
    {
        return new BulkUpdater($this->httpClient, $this->databaseName);
    }

    /**
     * Execute a POST request against CouchDB inserting a new document, leaving the server to generate a uuid.
     *
     * @param  array $data
     * @return array<id, rev>
     */
    public function postDocument(array $data)
    {
        $path = '/' . $this->databaseName;
        $response = $this->httpClient->request('POST', $path, json_encode($data));

        if ($response->status != 201) {
            throw HTTPException::fromResponse($path, $response);
        }

        return array($response->body['id'], $response->body['rev']);
    }

    /**
     * Execute a PUT request against CouchDB inserting or updating a document.
     * 
     * @param array $data
     * @param string $id
     * @param string|null $rev
     * @return array<id, rev>
     */
    public function putDocument($data, $id, $rev = null)
    {
        $data['_id'] = $id;
        if ($rev) {
            $data['_rev'] = $rev;
        }

        $path = '/' . $this->databaseName . '/' . urlencode($id);
        $response = $this->httpClient->request('PUT', $path, json_encode($data));

        if ($response->status != 201) {
            throw HTTPException::fromResponse($path, $response);
        }

        return array($response->body['id'], $response->body['rev']);
    }

    /**
     * Delete a document.
     * 
     * @param  string $id
     * @param  string $rev
     * @return void
     */
    public function deleteDocument($id, $rev)
    {
        $path = '/' . $this->databaseName . '/' . $id . '?rev=' . $rev;
        $response = $this->httpClient->request('DELETE', $path);

        if ($response->status != 200) {
            throw HTTPException::fromResponse($path, $response);
        }
    }

    /**
     * @param string $designDocName
     * @param string $viewName
     * @param DesignDocument $designDoc
     * @return View\Query
     */
    public function createViewQuery($designDocName, $viewName, DesignDocument $designDoc)
    {
        return new View\Query($this->httpClient, $this->databaseName, $designDocName, $viewName, $designDoc);
    }

    /**
     * Create a design document from the definition at the named location.
     * 
     * @param string $designDocName
     * @param DesignDocument $designDoc
     * @return HTTP\Response
     */
    public function createDesignDocument($designDocName, DesignDocument $designDoc)
    {
        $data = $designDoc->getData();
        $data['_id'] = '_design/' . $designDocName;

        return $this->httpClient->request(
            "PUT",
            sprintf("/%s/_design/%s", $this->databaseName, $designDocName),
            json_encode($data)
        );
    }
}
