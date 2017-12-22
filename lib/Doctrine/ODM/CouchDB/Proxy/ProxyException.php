<?php

namespace Doctrine\ODM\CouchDB\Proxy;

/**
 * CouchDB ODM Proxy Exception
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class ProxyException extends \Doctrine\ODM\CouchDB\CouchDBException {

    public static function proxyDirectoryRequired() {
        return new self("You must configure a proxy directory. See docs for details");
    }

    public static function proxyNamespaceRequired() {
        return new self("You must configure a proxy namespace. See docs for details");
    }

}
