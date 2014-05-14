<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\CouchDBException;

class AssignedIdGenerator extends IdGenerator
{
    /**
     * @param object $document
     * @param ClassMetadata $cm
     * @param DocumentManager $dm
     * @return array
     * @throws CouchDBException
     */
    public function generate($document, ClassMetadata $cm, DocumentManager $dm)
    {
        $id = $cm->getIdentifierValue($document);
        if (!$id) {
            throw CouchDBException::assignedIdGeneratorNoIdFound($cm->name);
        }
        return $id;
    }
}
