CouchDB Lucene Queries
======================

If you are using the `CouchDB-Lucene <https://github.com/rnewson/couchdb-lucene>`_ integration
you can make use of Lucene querying with Doctrine CouchDB ODM.

See the docs of CouchDB Lucene how you can create a design document with views for Lucene.

You can use the design-doc API of Doctrine, for example:

.. code-block:: php

    <?php

    use Doctrine\ODM\CouchDB\View\DesignDocument;

    class LuceneQueryDesignDoc implements DesignDocument
    {
        public function getData()
        {
            return array(
                "fulltext" => array(
                    "by_name" => array(
                        "index" => "function(doc) {
                            var ret = new Document();
                            ret.add(doc.name);
                            ret.add(doc.type, {field: \"type\"});
                            return ret;
                        }"
                    )
                ),
            );
        }
    }

You can use the CouchDB Client to create the design document for this lucene view.

When you have created a CouchDB Lucene view you can query it with Doctrine using
the ``DocumentManager#createLuceneQuery($designDocName, $viewName)`` method. A lucene
query instance extends ``AbstractQuery`` like the Doctrine CouchDB Map-Reduce queries.

An example showing all the functionalities of a lucene query looks like:

.. code-block:: php

    $query = $dm->createLuceneQuery("designDocName", "viewName");
    $result = $query->setQuery("+bar -foo")
          ->setSkip(20)
          ->setLimit(100)
          ->setIncludeDocs(true)
          ->onlyDocs(true)
          ->setStale(false)
          ->setAnalyzer($analyzer)
          ->execute();

The result works as explained in the View and Map-Reduce Queries section.
