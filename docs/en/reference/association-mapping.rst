Association Mapping
===================

Graph Traversal and Identity Map
--------------------------------

Doctrine CouchDB offers you a fluent experience with your CouchDB documents. All relationships are fully
traversable through lazy loading. You essentially need to query only one document explicitly and can
then access all the others that are related by traversing the references in the objects.

A document manager contains an identity map, it will never return you different instances of PHP objects
for the document with the same ID.

Different Association Mappings
------------------------------

Doctrine CouchDB offers several different mechanisms to manage relations between documents:

-   Reference one document through its document-id
-   Reference many documents by saving all the document-ids in an array
-   Finding all documents that have a reference many relationship to the current document (inverse reference many/one)
-   Embedding a single document
-   Embedding an array of documents

ReferenceOne
~~~~~~~~~~~~

You can have a reference between two documents by a reference one relationship in the following way:

.. code-block:: php

    <?php
    /** @Document */
    class Article
    {
        /** @Id */
        private $id;

        /** @ReferenceOne(targetDocument="User") */
        private $author;
    }

This mapping definition would save the author id or null in the CouchDB document but let
you work with a Author object in PHP.

In CouchDB two documents with this relationship will look like:

.. code-block:: javascript

    {
        "_id": "1234",
        "doctrine_metadata":
        {
            "associations":
            {
                "author": "54321"
            }
        },
        "title": "An article",
        "type": "Article"
    }

    {
        "_id": "54321",
        "name": "Benjamin",
        "type": "User"
    }

ReferenceMany
~~~~~~~~~~~~~

You can have a reference between a document and a set of related documents in the following way

.. code-block:: php

    <?php
    /**
     * @Document
     */
    class Article
    {
        /** @Id */
        private $id;

        /** @ReferenceMany(targetDocument="Comment") */
        private $comments;
    }

This mapping definition will save an array of comment ids in every article document and
will present you with a ``Doctrine\Common\Collections\Collection`` containing Comment instances
in PHP.

In CouchDB documents with this relationship will look like:

.. code-block:: javascript

    {
        "_id": "1234",
        "comments": ["55555", "44444"],
        "doctrine_metadata":
        {
            "associations": ["comments"]
        },
        "title": "An article",
        "type": "Article"
    }

    {
        "_id": "55555",
        "text": "Thank you!",
        "type": "Comment"
    }

    {
        "_id": "44444",
        "text": "Very informative!",
        "type": "Comment"
    }

Inverse ReferenceMany
~~~~~~~~~~~~~~~~~~~~~

You can define the inverse side of a reference one or reference many association, which will
use a view to access which owning side documents point to the current document by holding
a reference to their id:

.. code-block:: php

    <?php
    /** @Document */
    class User
    {
        /** @Id */
        private $id;

        /**
         * @ReferenceMany(targetDocument="Article", mappedBy="author")
         */
        private $articles;
    }

See the difference between the previous reference many definition by using the mappedBy attribute.
This specifies which association on the target document contains the id reference.

In CouchDB documents with this relationship will look like:

.. code-block:: javascript

    {
        "_id": "54321",
        "name": "Benjamin",
        "type": "User"
    }

A view is used to lookup the related articles. The view emits type, all
associations and their ids.

EmbedOne
~~~~~~~~

You can embed a class into a document. Both will be saved in the same CouchDB document:

.. code-block:: php

    <?php
    /** @Document */
    class User
    {
        /** @Id */
        private $id;

        /** @EmbedOne */
        private $address;
    }

The embed one mapping definition does not necessarily need a "targetDocument" attribute,
it can detect and save this automatically.

In CouchDB documents with this relationship will look like:

.. code-block:: javascript

    {
        "_id": "1234",
        "address":
        {
            "zipcode": "12345",
            "city": "Berlin"
        }
    }

EmbedMany
~~~~~~~~~

You can embed an array of classes into a document.

.. code-block:: php

    <?php
    /** @Document */
    class User
    {
        /** @Id */
        private $id;

        /** @EmbedMany */
        private $phonenumbers;
    }

In CouchDB documents with this relationship will look like:

.. code-block:: javascript

    {
        "_id": "1234",
        "phonenumbers":
        [
            {"number": "+1234567890"},
            {"number": "+1234567891"}
        ]
    }
