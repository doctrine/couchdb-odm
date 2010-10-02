<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class CouchUUIDGenerator extends IdGenerator
{
    private $uuids = array();

    public function generate($document, ClassMetadata $cm, DocumentManager $dm)
    {
        if (count($this->uuids) == 0) {
            $UUIDGenerationBufferSize = $dm->getConfiguration()->getUUIDGenerationBufferSize();
            $this->uuids = $dm->getUnitOfWork()->getDocumentPersister()->getUuids($UUIDGenerationBufferSize);
        }

        $id =  array_pop($this->uuids);
        $cm->reflFields[$cm->identifier]->setValue($document, $id);
        return $id;
    }
}