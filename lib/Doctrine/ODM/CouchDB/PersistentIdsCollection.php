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

use Doctrine\Common\Collections\Collection;

class PersistentIdsCollection extends PersistentCollection
{
    private $documentName;
    private $dm;
    private $ids;

    public function __construct(Collection $collection, $documentName, DocumentManager $dm, $ids)
    {
        $this->col = $collection;
        $this->documentName = $documentName;
        $this->dm = $dm;
        $this->ids = $ids;
        $this->isInitialized = (count($ids) == 0);
    }

    public function initialize()
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;

            $elements = $this->col->toArray();
            $this->col->clear();

            $objects = $this->dm->getUnitOfWork()->findMany($this->ids, $this->documentName);
            foreach ($objects AS $key => $object) {
                $this->col->set($key, $object);
            }
            // append old elements
            foreach ($elements AS $object) {
                $this->col->add($object);
            }
        }
    }
}
