<?php

namespace Doctrine\ODM\CouchDB\Id;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

/**
 * Used to abstract ID generation
 */
abstract class IdGenerator
{
    static private $generators = array();

    /**
     * @param  int $generatorType
     * @return IdGenerator
     */
    static public function get($generatorType)
    {
        if (!isset(self::$generators[$generatorType])) {
            switch ($generatorType) {
                case ClassMetadata::IDGENERATOR_ASSIGNED:
                    $instance = new AssignedIdGenerator();
                    break;
                case ClassMetadata::IDGENERATOR_UUID:
                    $instance = new CouchUUIDGenerator();
                    break;
                default:
                    throw \Exception("ID Generator does not exist!");
            }

            self::$generators[$generatorType] = $instance;
        }
        return self::$generators[$generatorType];
    }

    /**
     * @param object $document
     * @param ClassMetadata $cm
     * @param DocumentManager $dm
     */
    abstract public function generate($document, ClassMetadata $cm, DocumentManager $dm);
}