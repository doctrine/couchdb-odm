<?php

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
