<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Collections\Collection;

class PersistentViewCollection extends PersistentCollection
{
    private $dm;
    private $owningDocumentId;
    private $assoc;

    public function __construct(Collection $collection, DocumentManager $dm, $owningDocumentId, $assoc)
    {
        $this->col = $collection;
        $this->dm = $dm;
        $this->owningDocumentId = $owningDocumentId;
        $this->assoc = $assoc;
        $this->isInitialized = false;
    }

    public function initialize()
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;

            $elements = $this->col->toArray();
            $this->col->clear();

            $relatedObjects = $this->dm->createNativeQuery('doctrine_associations', 'inverse_associations')
                                  ->setStartKey(array($this->owningDocumentId, $this->assoc['mappedBy']))
                                  ->setEndKey(array($this->owningDocumentId, $this->assoc['mappedBy'], 'z'))
                                  ->setIncludeDocs(true)
                                  ->execute();

            $uow = $this->dm->getUnitOfWork();
            foreach ($relatedObjects AS $relatedRow) {
                $this->col->add($uow->createDocument($this->assoc['targetDocument'], $relatedRow['doc']));
            }

            // append old elements
            foreach ($elements AS $object) {
                $this->col->add($object);
            }
        }
    }
}
