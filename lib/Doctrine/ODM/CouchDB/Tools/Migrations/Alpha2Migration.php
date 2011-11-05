<?php

namespace Doctrine\ODM\CouchDB\Tools\Migrations;

use Doctrine\CouchDB\Tools\Migrations\AbstractMigration;

class Alpha2Migration extends AbstractMigration
{
    protected function migrate(array $docData)
    {
        $migrate = false;
        if (isset($docData['doctrine_metadata']['type'])) {
            $docData['type'] = $docData['doctrine_metadata']['type'];
            unset($docData['doctrine_metadata']['type']);
            $migrate = true;
        }
        if (isset($docData['doctrine_metadata']['associations'])) {
            $associations = array();
            foreach ($docData['doctrine_metadata']['associations'] AS $name => $values) {
                $docData[$name] = $values;
                $associations[] = $name;
            }
            $docData['doctrine_metadata'] = $associations;
            $migrate = true;
        }

        return ($migrate) ? $docData : null;
    }
}