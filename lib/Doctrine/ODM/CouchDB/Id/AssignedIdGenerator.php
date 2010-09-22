<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class AssignedIdGenerator extends IdGenerator
{
    /**
     * @param object $document
     * @param ClassMetadata $cm
     * @param DocumentManager $dm
     * @return array
     */
    public function generate($document, ClassMetadata $cm, DocumentManager $dm)
    {
        $id = $cm->getIdentifierValue($document);
        if (!$id) {
            throw new \Exception("no id");
        }
        return $id;
    }
}