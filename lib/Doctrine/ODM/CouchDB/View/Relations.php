<?php

namespace Doctrine\ODM\CouchDB\View;

class Relations extends Handler
{
    /**
     * Get related objects
     * 
     * @param string $id 
     * @param string $type 
     * @return array
     */
    public function getRelatedObjects( $id, $type )
    {
        return $this->getViewResult(
            'relations',
            array(
                "startkey"     => array( $id, $type ),
                // @TODO: replace "z" by \u9999
                "endkey"       => array( $id, $type, "z" ),
                "include_docs" => true,
            )
        );
    }

    /**
     * Get related objects
     * 
     * @param string $id 
     * @param string $type 
     * @return array
     */
    public function getReverseRelatedObjects( $id, $type )
    {
        return $this->getViewResult(
            'reverse_relations',
            array(
                "startkey"     => array( $id, $type ),
                // @TODO: replace "z" by \u9999
                "endkey"       => array( $id, $type, "z" ),
                "include_docs" => true,
            )
        );
    }

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
    protected function getViews()
    {
        return array(
            'relations' => array(
                'map' => 'function (doc)
{
    if (doc.doctrine_metadata &&
        doc.doctrine_metadata.relations)
    {
        var relations = doc.doctrine_metadata.relations;
        for ( type in relations )
        {
            for ( var i = 0; i < relations[type].length; ++i )
            {
                emit([doc._id, type, relations[type][i]], {"_id": relations[type][i]} );
            }
        }
    }
}',
            ),
            'reverse_relations' => array(
                'map' => 'function (doc)
{
    if (doc.doctrine_metadata &&
        doc.doctrine_metadata.relations)
    {
        var relations = doc.doctrine_metadata.relations;
        for ( type in relations )
        {
            for ( var i = 0; i < relations[type].length; ++i )
            {
                emit([relations[type][i], doc.doctrine_metadata.type, doc._id], {"_id": doc._id} );
            }
        }
    }
}',
            ),
        );
    }
}
