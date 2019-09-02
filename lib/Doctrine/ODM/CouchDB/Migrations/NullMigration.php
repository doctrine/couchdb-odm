<?php

namespace Doctrine\ODM\CouchDB\Migrations;

/**
 * Default migration that changes no document
 */
class NullMigration
{
    /**
     * {@inheritDoc}
     */
    public function migrate(array $data)
    {
        return $data;
    }
}
