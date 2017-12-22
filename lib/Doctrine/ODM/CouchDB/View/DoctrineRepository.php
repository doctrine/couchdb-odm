<?php


namespace Doctrine\ODM\CouchDB\View;

use Doctrine\CouchDB\View\DesignDocument;

/**
 * Repository queries
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineRepository implements DesignDocument
{
    public function getData()
    {
        $mapRepositoryEqualConstraint = <<<'JS'
function (doc)
{
    if (doc.doctrine_metadata
        && doc.doctrine_metadata.indexes
    ) {
        var indexes = doc.doctrine_metadata.indexes;
        for ( idx in indexes ) {
            if (doc[indexes[idx]] != null) {
                emit([doc.type, indexes[idx], doc[indexes[idx]]], {"_id": doc._id} );
            }
        }
    }
}
JS;

        $mapRepositoryTypeConstraint = <<<'JS'
function (doc)
{
    if (doc.type
        && doc.doctrine_metadata
        && doc.doctrine_metadata.indexed) {
        emit(doc.type, {"_id": doc._id} );
    }
}
JS;

        return array(
            'views' => array(
                'equal_constraint' => array(
                    'map' => $mapRepositoryEqualConstraint,
                ),
                'type_constraint' => array(
                    'map' => $mapRepositoryTypeConstraint,
                ),
            )
        );
    }
}
