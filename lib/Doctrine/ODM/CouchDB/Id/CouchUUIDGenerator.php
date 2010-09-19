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
            // TODO: Allow to configure UUID Generation number
            $this->uuids = $dm->getCouchDBClient()->getUuids(20);
        }

        $id =  array (0 => array_pop($this->uuids));
        $cm->reflProps[$cm->identifier[0]]->setValue($document, $id[0]);
        return $id;
    }
}