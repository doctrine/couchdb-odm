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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Persistence\ObjectRepository;

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
class DocumentRepository implements ObjectRepository
{
    /**
     * @var string
     */
    protected $documentName;

    /**
     * @var string
     */
    protected $documentType;

    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * @var Mapping\ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>DocumentRepository</tt>.
     *
     * @param DocumentManager $dm The DocumentManager to use.
     * @param Mapping\ClassMetadata $class The class descriptor.
     */
    public function __construct($dm, Mapping\ClassMetadata $class)
    {
        $this->documentName = $class->name;
        $this->documentType = str_replace("\\", ".", $this->documentName);
        $this->dm = $dm;
        $this->class = $class;
    }

    /**
     * Find a single document by its identifier
     *
     * @param mixed $id A single identifier or an array of criteria.
     * @return object|null $document
     */
    public function find($id)
    {
        $uow = $this->dm->getUnitOfWork();

        $document = $uow->tryGetById($id);

        if ($document === false) {
            $response = $this->dm->getCouchDBClient()->findDocument($id);
            if ($response->status == 404) {
                return null;
            }

            $hints = array();
            $document = $uow->createDocument($this->documentName, $response->body, $hints);
        }

        return $document;
    }

    /**
     * @param  object $document
     * @return void
     */
    final public function refresh($document)
    {
        $uow = $this->dm->getUnitOfWork();
        $uow->refresh($document);
    }

    /**
     * Find Many documents of the given repositories type by id.
     *
     * @param array $ids
     * @param null|int $limit
     * @param null|int $offset
     * @return array
     */
    public function findMany(array $ids, $limit = null, $offset = null)
    {
        $uow = $this->dm->getUnitOfWork();
        return $uow->findMany($ids, $this->documentName, $limit, $offset);
    }

    public function findAll()
    {
        return $this->dm->createQuery('doctrine_repositories', 'type_constraint')
                        ->setKey($this->documentType)
                        ->setIncludeDocs(true)
                        ->toArray(true)
                        ->execute();
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        if (count($criteria) == 1) {
            foreach ($criteria AS $field => $value) {
                $query = $this->dm->createQuery('doctrine_repositories', 'equal_constraint')
                                  ->setKey(array($this->documentType, $field, $value))
                                  ->setIncludeDocs(true)
                                  ->toArray(true);
                if ($limit) {
                   $query->setLimit($limit);
                }
                if ($offset) {
                   $query->setSkip($offset);
                }
                return $query->execute();
            }
        } else {
            $ids = array();
            $num = 0;
            foreach ($criteria AS $field => $value) {
                $ids[$num] = array();
                $result = $this->dm->createNativeQuery('doctrine_repositories', 'equal_constraint')
                                   ->setKey(array($this->documentType, $field, $value))
                                   ->execute();
                foreach ($result aS $doc) {
                    $ids[$num][] = $doc['id'];
                }
                $num++;
            }
            $mergeIds = $ids[0];
            for ($i = 1; $i < $num; $i++) {
                $mergeIds = array_intersect($mergeIds, $ids[$i]);
            }

            return $this->findMany(array_values($mergeIds), $limit, $offset);
        }
    }

    public function findOneBy(array $criteria)
    {
        $docs = $this->findBy($criteria);
        return isset($docs[0]) ? $docs[0] : null;
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

    public function getClassName()
    {
        return $this->getDocumentName();
    }
}
