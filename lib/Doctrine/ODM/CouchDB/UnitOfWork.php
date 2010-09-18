<?php

namespace Doctrine\ODM\CouchDB;

class UnitOfWork
{
    const STATE_NEW = 1;
    const STATE_MANAGED = 2;
    const STATE_REMOVED = 3;

    /**
     * @var DocumentManager
     */
    private $dm = null;

    private $identityMap = array();

    /**
     * @var array
     */
    private $scheduledInsertions = array();

    private $documentState = array();

    /**
     * @var array
     */
    private $scheduledUpdates = array();

    /**
     * @var array
     */
    private $scheduledRemovals = array();

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function createDocument($class, $data)
    {
        $metadata = $this->dm->getClassMetadata($class);

        $idHash = array();
        foreach ($metadata->identifier AS $idProperty) {
            $idHash[] = $data[$idProperty];
        }
        $idHash = implode(" ", $idHash);

        $overrideLocalValues = true;
        if (isset($this->identityMap[$metadata->name][$idHash])) {
            $doc = $this->identityMap[$metadata->name][$idHash];
            $overrideLocalValues = false;

            if ($doc instanceof Proxy && $doc->__isInitialized__) {
                $overrideLocalValues = true;
            }
        } else {
            $doc = $metadata->newInstance();
            $this->identityMap[$metadata->name][$idHash] = $doc;
        }

        if ($overrideLocalValues) {
            foreach ($metadata->reflProps AS $prop => $reflProp) {
                /* @var $reflProp ReflectionProperty */
                $reflProp->setValue($doc, $data[$prop]);
            }
        }

        $this->documentState[spl_object_hash($doc)] = self::STATE_MANAGED;

        return $doc;
    }

    public function scheduleInsert($object)
    {
        if ($this->getDocumentState($object) != self::STATE_NEW) {
            throw new \Exception("Object is already managed!");
        }

        $cm = $this->dm->getClassMetadata(get_class($object));

        if ($cm->idGenerator == Mapping\ClassMetadata::IDGENERATOR_ASSIGNED) {
            $id = $cm->getIdentifierValues($object);
            if (!$id) {
                throw new \Exception("no id");
            }
        }

        $oid = \spl_object_hash($object);
        $this->scheduledInsertions[$oid] = $object;
    }

    public function scheduleRemove($object)
    {
        $oid = \spl_object_hash($object);
        $this->scheduledInsertions[$oid] = $object;
    }

    public function getDocumentState($object)
    {
        $oid = \spl_object_hash($object);
        if (isset($this->documentState[$oid])) {
            return $this->documentState[$oid];
        }
        return self::STATE_NEW;
    }

    public function flush()
    {
        foreach ($this->scheduledInsertions AS $entity) {
            
        }
    }
}