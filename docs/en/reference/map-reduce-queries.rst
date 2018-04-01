Views and Map-Reduce Queries
============================

CouchDB uses views filtered through map-reduce to query all the documents of your database. Each view
has a map- and optionally a reduce-function. Doctrine CouchDB ODM allows you to create and query views
in your application.

Creating and Managing Views
---------------------------

Views are best managed as a folder structure in the filesystem. Create a Directory "couchdb/views"
and instantiate a ``FolderDesignDocument`` in the following way and create the
design document in the database:

.. code-block:: php

    <?php
    use Doctrine\CouchDB\View\FolderDesignDocument;

    $view = new FolderDesignDocument("path/to/app/couchdb");
    $designDocJson = $view->getData();

    $couchClient->createDesignDocument("myapp", $view);
    
If the directory structure now looked like the following:

::

    couchdb/
        views/
            username/
                map.js
            article-dates/
                map.js
                reduce.js

It will create two views "username" and "article-dates" with
corresponding map and reduce functions. For example
the username map.js might look like:

.. code-block:: javascript

    function(doc) {
        if (doc.type == 'Doctrine.Tests.Models.CMS.CmsUser') {
            emit(doc.username, doc._id);
        }
    }

Querying Views
--------------

To query a view from Doctrine CouchDB ODM you have to register it with its design document name
in the CouchDB ODM Configuration:

.. code-block:: php

    <?php

    $config = $dm->getConfiguration();
    $config->addDesignDocument(
        "myapp", 
        "Doctrine\CouchDB\View\FolderDesignDocument",
        "path/to/app/couchdb"
    );

You can then create either a native or a odm-query by calling
either ``DocumentManager#createNativeQuery($designDocName, $viewName)`` or
``DocumentManager#createQuery($designDocName, $viewName)``.

The difference between both queries is their result and some parameters. The ODM query will
return instances of php objects that map to the CouchDB documents and
the native query will return only convert the json to arrays that have been fetched from
the CouchDB.

Both queries have a common base class with a simple API:

.. code-block:: php

    <?php

    namespace Doctrine\CouchDB\View;

    abstract class AbstractQuery
    {
        /**
         * Get HTTP Query Parameter 
         *
         * @param  string $key
         * @return mixed
         */
        public function getParameter($key);

        /**
         * Query the view with the current params.
         *
         * @return Doctrine\CouchDB\View\Result
         */
        public function execute();

        /**
         * Create design document for this query.
         *
         * Method is used internally when querying the view and it doesnt exist yet.
         *
         * @return void
         */
        public function createDesignDocument();
    }

With both query types you just call execute() to retrieve the result from the database.

The following query parameter related methods exist in both the native and odm-query:

.. code-block:: php

    <?php
    namespace Doctrine\CouchDB\View;

    class Query extends AbstractQuery
    {
        /**
         * Find key in view.
         *
         * @param  string $val
         * @return Query
         */
        public function setKey($val);

        /**
         * Set starting key to query view for.
         *
         * @param  string $val
         * @return Query
         */
        public function setStartKey($val);

        /**
         * Set ending key to query view for.
         *
         * @param  string $val
         * @return Query
         */
        public function setEndKey($val);

        /**
         * Document id to start with
         *
         * @param  string $val
         * @return Query
         */
        public function setStartKeyDocId($val);

        /**
         * Last document id to include in the output
         *
         * @param  string $val
         * @return Query
         */
        public function setEndKeyDocId($val);

        /**
         * Limit the number of documents in the output
         *
         * @param  int $val
         * @return Query
         */
        public function setLimit($val);

        /**
         * Skip n number of documents
         *
         * @param  int $val
         * @return Query
         */
        public function setSkip($val);

        /**
         * If stale=ok is set CouchDB will not refresh the view even if it is stalled.
         *
         * @param  bool $flag
         * @return Query
         */
        public function setStale($flag);

        /**
         * reverse the output
         *
         * @param  bool $flag
         * @return Query
         */
        public function setDescending($flag);

        /**
         * The group option controls whether the reduce function reduces to a set of distinct keys or to a single result row.
         *
         * @param  bool $flag
         * @return Query
         */
        public function setGroup($flag);

        public function setGroupLevel($level);

        /**
         * Use the reduce function of the view. It defaults to true, if a reduce function is defined and to false otherwise.
         *
         * @param  bool $flag
         * @return Query
         */
        public function setReduce($flag);

        /**
         * Controls whether the endkey is included in the result. It defaults to true.
         *
         * @param  bool $flag
         * @return Query
         */
        public function setInclusiveEnd($flag);

        /**
         * Automatically fetch and include the document which emitted each view entry
         *
         * @param  bool $flag
         * @return Query
         */
        public function setIncludeDocs($flag);
    }

There is a single additional method on the ODM Query that specifies if
you just want to return the documents associated with a view result:

.. code-block:: php

    <?php
    namespace Doctrine\ODM\CouchDB\View;

    class ODMQuery extends Query
    {
        public function onlyDocs($flag);
    }

An example execution of the username view given above looks like:

.. code-block:: php

    <?php

    $query = $dm->createQuery("myapp", "username");
    $result = $query->setStartKey("b")
          ->setEndKey("c")
          ->setLimit(100)
          ->setSkip(20)
          ->onlyDocs(true)
          ->execute();

This will return all usernames starting with "b" and ending with "c",
skipping the first 20 results and limiting the result to 100 documents.

View Results
------------

The result of a view is an instance of ``Doctrine\CouchDB\View\Result``.
It implements ``Countable``, ``IteratorAggregate`` and ``ArrayAccess``.
If you specify ``onlyDocs(true)`` each result-row will contain only
the associated document, otherwise the document is on the row index "doc"
of the query.

The following snippet shows the difference:

.. code-block:: php

    <?php

    $query = $dm->createQuery("myapp", "username");
    $result = $query->setStartKey("b")
          ->setEndKey("c")
          ->setLimit(100)
          ->setSkip(20)
          ->onlyDocs(true)
          ->execute();

    foreach ($result AS $user) {
        echo $user->getUsername() . "\n";
    }

    $query->onlyDocs(false);
    $result = $query->execute();

    foreach ($result AS $userRow) {
        echo $userRow['doc']->getUsername() . "\n";
    }

