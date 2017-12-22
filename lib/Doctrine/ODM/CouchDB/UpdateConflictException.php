<?php

namespace Doctrine\ODM\CouchDB;

/**
 * Thrown by the UnitOfWork when errors happen on flush.
 *
 * Contains all the documents that produced errors while batch-updating them.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class UpdateConflictException extends \Exception
{
    /**
     * @var array
     */
    private $updateConflictDocuments = array();

    /**
     * @param array $updateConflictDocuments
     */
    public function __construct($updateConflictDocuments)
    {
        $this->updateConflictDocuments = $updateConflictDocuments;
    }

    /**
     * @return array
     */
    public function getUpdateConflictDocuments()
    {
        return $this->updateConflictDocuments;
    }
}
