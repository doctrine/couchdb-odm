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

/**
 * An DocumentRepository serves as a repository for documents with generic as well as
 * business specific methods for retrieving documents.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate documents.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class DocumentRepository
{
    /**
     * @var string
     */
    protected $documentName;

    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * @var Doctrine\ODM\CouchDB\Mapping\ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>DocumentRepository</tt>.
     *
     * @param DocumentManager $dm The DocumentManager to use.
     * @param ClassMetadata $classMetadata The class descriptor.
     */
    public function __construct($dm, Mapping\ClassMetadata $class)
    {
        $this->documentName = $class->name;
        $this->dm = $dm;
        $this->class = $class;
    }

    /**
     * Find a single document by its identifier
     *
     * @param mixed $query A single identifier or an array of criteria.
     * @param array $select The fields to select.
     * @return Doctrine\ODM\CouchDB\MongoCursor $cursor
     * @return object $document
     */
    public function find($id)
    {
        $uow = $this->dm->getUnitOfWork();
        $response = $this->dm->getCouchDBClient()->findDocument($id);

        if ($response->status == 404) {
            return null;
        }

        $hints = array();
        return $uow->createDocument($this->documentName, $response->body, $hints);
    }

    /**
     * @param  object $document
     * @return void
     */
    public function refresh($document)
    {
        $uow = $this->dm->getUnitOfWork();
        $response = $this->dm->getCouchDBClient()->findDocument($uow->getDocumentIdentifier($document));

        if ($response->status == 404) {
            throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException();
        }

        $hints = array('refresh' => true);
        $uow->createDocument($this->documentName, $response->body, $hints);
    }

    /**
     * Find Many documents of the given repositories type by id.
     *
     * @param array $ids
     * @return array
     */
    public function findMany(array $ids)
    {
        $uow = $this->dm->getUnitOfWork();
        $response = $this->dm->getCouchDBClient()->findDocuments($ids);

        if ($response->status != 200) {
            throw new \Exception("loadMany error code " . $response->status);
        }

        $docs = array();
        if ($response->body['total_rows'] > 0) {
            foreach ($response->body['rows'] AS $responseData) {
                $docs[] = $uow->createDocument($this->documentName, $responseData['doc']);
            }
        }
        return $docs;
    }

    /**
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }

    /**
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * @return Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }
}
