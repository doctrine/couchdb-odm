<?php


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
