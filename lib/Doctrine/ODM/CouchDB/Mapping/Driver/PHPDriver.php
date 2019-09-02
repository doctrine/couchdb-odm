<?php

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\Common\Persistence\Mapping\Driver\PHPDriver as CommonPHPDriver;

/**
 * The PHPDriver invokes a static PHP function on the document class itself passing
 * a ClassMetadata instance for you to manually populate with mapping information.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class PHPDriver extends CommonPHPDriver
{
}
