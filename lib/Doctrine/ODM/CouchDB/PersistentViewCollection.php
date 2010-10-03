<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Collections\Collection;

class PersistentViewCollection extends PersistentCollection
{
    private $dm;
    private $owningDocumentId;
    private $assocFieldName;
    public $isInitialized = false;

    public function __construct(Collection $collection, DocumentManager $dm, $owningDocumentId, $assocFieldName)
    {
        $this->col = $collection;
        $this->dm = $dm;
        $this->owningDocumentId = $owningDocumentId;
        $this->assocFieldName = $assocFieldName;
        $this->isInitialized = false;
    }

    protected function load()
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
                $this->col->add($uow->createDocument($relatedRow['doc']['doctrine_metadata']['type'], $relatedRow['doc']));
            }

            foreach ($elements AS $object) {
                $this->col->add($object);
            }
        }
    }
}