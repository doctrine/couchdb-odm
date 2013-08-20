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

use Doctrine\CouchDB\View\LuceneQuery;
use Doctrine\ODM\CouchDB\DocumentManager;

class ODMLuceneQuery extends LuceneQuery
{
    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var bool
     */
    private $onlyDocs = false;

    private $documentName;

    public function getDocumentName()
    {
        return $this->documentName;
    }

    public function setDocumentName($documentName)
    {
        $this->documentName = $documentName;
        return $this;
    }

    public function execute()
    {
        $response = $this->doExecute();
        if ($this->dm && $this->getParameter('include_docs') === true) {
            $uow = $this->dm->getUnitOfWork();
            foreach ($response->body['rows'] AS $k => $v) {
                if (!isset($v['type']) && !$this->documentName) {
                    throw new \InvalidArgumentException(
                        "Cannot query " . $this->getHttpQuery() . " lucene and convert to document instances, ".
                        "the type of document " . $v['id'] . " is not stored in Lucene. You can query without " .
                        "include_docs and pass the ids to findMany() of the repository you know this document is " .
                        "a type of.");
                }
                $v['type'] = isset($v['type']) ? $v['type'] : $this->documentName;

                $doc = $this->dm->find(str_replace(".", "\\", $v['type']), $v['id']);
                if ($this->onlyDocs) {
                    $response->body['rows'][$k] = $doc;
                } else {
                    $response->body['rows'][$k]['doc'] = $doc;
                }
            }
        }

        return $this->createResult($response);
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
     * @return LuceneQuery
     */
    public function onlyDocs($flag)
    {
        $this->setIncludeDocs(true);
        $this->onlyDocs = $flag;
        return $this;
    }
}
