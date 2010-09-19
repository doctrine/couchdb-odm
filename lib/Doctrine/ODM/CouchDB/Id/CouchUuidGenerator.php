<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class CouchUuidGenerator extends IdGenerator
{
    private $uuids = array();

    public function generate($document, ClassMetadata $cm, DocumentManager $dm)
    {
        // TODO: Allow to configure UUID Generation number
        if (count($this->uuids) == 0) {
            $client = $dm->getConfiguration()->getHttpClient();
            $response = $client->request('GET', '/_uuids');

            $this->uuids = $response->body['uuids'];
        }

        $id =  array (0 => array_pop($this->uuids));
        $cm->reflProps[$cm->identifier[0]]->setValue($document, $id[0]);
        return $id;
    }
}