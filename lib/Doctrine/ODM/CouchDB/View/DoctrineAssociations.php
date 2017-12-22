<?php

namespace Doctrine\ODM\CouchDB\View;

use Doctrine\CouchDB\View\DesignDocument;

/**
 * Associations class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineAssociations implements DesignDocument
{
    /**
     * Get view code
     *
     * Return the view code, which should be comitted to the database, which
     * should be structured like:
     *
     * <code>
     *  array(
     *      "name" => array(
     *          "map"     => "code",
     *          ["reduce" => "code"],
     *      ),
     *      ...
     *  )
     * </code>
     */
    public function getData()
    {
        $mapInverseAssociations = <<<'JS'
function (doc)
{
    if (doc.doctrine_metadata
        && doc.doctrine_metadata.associations
    ) {
        for ( var j= 0; j < doc.doctrine_metadata.associations.length; j++ ) {
            var assocName = doc.doctrine_metadata.associations[j];
            if (doc[assocName] != null) {
                if (typeof doc[assocName] == 'object') {
                    for ( var i = 0; i < doc[assocName].length; ++i ) {
                        emit([doc[assocName][i], assocName, doc._id], {"_id": doc._id} );
                    }
                } else {
                    emit([doc[assocName], assocName, doc._id], {"_id": doc._id} );
                }
            }
        }
    }
}
JS;

        return array(
            'views' => array(
                'inverse_associations' => array(
                    'map' => $mapInverseAssociations,
                ),
            )
        );
    }
}
