<?php

namespace Doctrine\ODM\CouchDB\Migrations;

/**
 * CouchDB documents can be migrated whenever they are loaded.
 *
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
interface DocumentMigration
{
    /**
     * Accept a CouchDB document and migrate data, return new document.
     *
     * This method has to return the original data, when no migration
     * needs to take place.
     *
     * @param array $data
     * @return array
     */
    public function migrate(array $data);
}
