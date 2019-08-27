<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ORM\EntityNotFoundException;

/**
 * Exception thrown when a Proxy fails to retrieve a Document.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Nils Adermann <naderman@naderman.de>
 */
class DocumentNotFoundException extends CouchDBException
{
    public function __construct($message = null)
    {
        parent::__construct($message ?? 'Document was not found.');
    }

	/**
	 * Static constructor.
	 *
	 * @param string  $className
	 * @param mixed $id
	 *
	 * @return self
	 */
	public static function fromClassNameAndIdentifier($className, $id)
	{
		$ids = [];

		foreach ($id as $key => $value) {
			$ids[] = $key . '(' . $value . ')';
		}

		return new self(
			'Document of type \'' . $className . '\' for ID ' .  $id . ' was not found'
		);
	}
}
