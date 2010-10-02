<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Collections\Collection;

class PersistentIdsCollection extends PersistentCollection
{
    private $documentName;
    private $dm;
    private $ids;
    public $isInitialized = false;

    public function __construct(Collection $collection, $documentName, DocumentManager $dm, $ids)
    {
        $this->col = $collection;
        $this->documentName = $documentName;
        $this->dm = $dm;
        $this->ids = $ids;
        $this->isInitialized = (count($ids) == 0);
    }

    protected function load()
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;

            $elements = $this->col->toArray();

            $repository = $this->dm->getDocumentRepository($this->documentName);
            $objects = $repository->findMany($this->ids);
            foreach ($objects AS $object) {
                $this->col->add($object);
            }
            foreach ($elements AS $object) {
                $this->col->add($object);
            }
        }
    }
}