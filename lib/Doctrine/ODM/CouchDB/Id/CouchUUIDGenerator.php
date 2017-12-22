<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

/**
 * UUID generator class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class CouchUUIDGenerator extends IdGenerator
{
    private $uuids = array();

    public function generate($document, ClassMetadata $cm, DocumentManager $dm)
    {
        if (empty($this->uuids)) {
            $UUIDGenerationBufferSize = $dm->getConfiguration()->getUUIDGenerationBufferSize();
            $this->uuids = $dm->getCouchDBClient()->getUuids($UUIDGenerationBufferSize);
        }

        $id = array_pop($this->uuids);
        $cm->reflFields[$cm->identifier]->setValue($document, $id);
        return $id;
    }
}
