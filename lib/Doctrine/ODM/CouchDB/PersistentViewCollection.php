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

use Doctrine\Common\Collections\Collection;

class PersistentViewCollection extends PersistentCollection
{
    private $dm;
    private $owningDocumentId;
    private $assocFieldName;

    public function __construct(Collection $collection, DocumentManager $dm, $owningDocumentId, $assocFieldName)
    {
        $this->col = $collection;
        $this->dm = $dm;
        $this->owningDocumentId = $owningDocumentId;
        $this->assocFieldName = $assocFieldName;
        $this->isInitialized = false;
    }

    public function initialize()
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;

            $elements = $this->col->toArray();

            $relatedObjects = $this->dm->createNativeQuery('doctrine_associations', 'inverse_associations')
                                  ->setStartKey(array($this->owningDocumentId, $this->assocFieldName))
                                  ->setEndKey(array($this->owningDocumentId, $this->assocFieldName, 'z'))
                                  ->setIncludeDocs(true)
                                  ->execute();

            $uow = $this->dm->getUnitOfWork();
            foreach ($relatedObjects AS $relatedRow) {
                $this->col->add($uow->createDocument(null, $relatedRow['doc']));
            }

            foreach ($elements AS $object) {
                $this->col->add($object);
            }
        }
    }
}