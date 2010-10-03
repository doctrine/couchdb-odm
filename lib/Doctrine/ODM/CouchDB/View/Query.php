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


namespace Doctrine\ODM\CouchDB\View;

use Doctrine\ODM\CouchDB\DocumentManager;

class Query extends NativeQuery
{
    /**
     * @var DocumentManager
     */
    private $dm;

    private $onlyDocs = false;

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
        $this->includeDocs(true);
        $this->onlyDocs = $flag;
        return $this;
    }

    /**
     * Query the view with the current params.
     *
     * @return array
     */
    public function execute()
    {
        $result = parent::execute();
        if ($this->getParameter('include_docs') === true) {
            $uow = $this->dm->getUnitOfWork();
            foreach ($result AS $k => $v) {
                $doc = $uow->createDocument($v['doc']['doctrine_metadata']['type'], $v['doc']);
                if ($this->onlyDocs) {
                    $result[$k] = $doc;
                } else {
                    $result[$k]['doc'] = $doc;
                }
            }
        }
        return $result;
    }
}