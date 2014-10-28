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

namespace Doctrine\ODM\CouchDB\View;

use Doctrine\CouchDB\View\Query;
use Doctrine\ODM\CouchDB\DocumentManager;

class ODMQuery extends Query
{
    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var bool
     */
    private $onlyDocs = false;

    private $toArray = false;

    public function execute()
    {
        $response = $this->doExecute();
        $data = array();
        if ($this->dm && $this->getParameter('include_docs') === true) {
            $uow = $this->dm->getUnitOfWork();
            foreach ($response->body['rows'] AS $k => $v) {
                $doc = $uow->createDocument(null, $v['doc']);
                if ($this->toArray) {
                    $data[] = $doc;
                } else if ($this->onlyDocs) {
                    $response->body['rows'][$k] = $doc;
                } else {
                    $response->body['rows'][$k]['doc'] = $doc;
                }
            }
        }

        return ($this->toArray) ? $data : $this->createResult($response);
    }


    /**
     * @param DocumentManager $dm
     */
    public function setDocumentManager(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    /**
     * @param  bool $flag
     * @return Query
     */
    public function onlyDocs($flag)
    {
        if ($flag) {
            $this->setIncludeDocs(true);
        }
        $this->onlyDocs = $flag;
        return $this;
    }

    public function toArray($flag)
    {
        $this->toArray = $flag;
        return $this;
    }
}
