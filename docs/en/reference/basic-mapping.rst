Basic Mapping
=============

This chapter explains the basic mapping of objects and properties.
Mapping of associations will be covered in the next chapter
"Association Mapping".

Introduction to Docblock Annotations
------------------------------------

You've probably used docblock annotations in some form already,
most likely to provide documentation metadata for a tool like
``PHPDocumentor`` (@author, @link, ...). Docblock annotations are a
tool to embed metadata inside the documentation section which can
then be processed by some tool. Doctrine generalizes the concept
of docblock annotations so that they can be used for any kind of
metadata and so that it is easy to define new docblock annotations.
In order to allow more involved annotation values and to reduce the
chances of clashes with other docblock annotations, the Doctrine 2
docblock annotations feature an alternative syntax that is heavily
inspired by the Annotation syntax introduced in Java 5.

The implementation of these enhanced docblock annotations is
located in the ``Doctrine\Common\Annotations`` namespace and
therefore part of the Common package. Doctrine docblock
annotations support namespaces and nested annotations among other
things. The Doctrine CouchDB ODM defines its own set of docblock
annotations for supplying object-relational mapping metadata.

    **NOTE** If you're not comfortable with the concept of docblock
    annotations, don't worry, as mentioned earlier Doctrine 2 provides
    XML and YAML alternatives and you could easily implement your own
    favourite mechanism for defining metadata.

Persistent classes
------------------

In order to mark a class for CouchDB persistence it needs
to be designated as an document. This can be done through the
``@Document`` marker annotation.

.. code-block:: php

    <?php
    /** @Document */
    class MyPersistentClass
    {
        //...
    }

Doctrine Mapping Types
----------------------

A Doctrine Mapping Type defines the mapping between a PHP type and
an JSON type. Since CouchDB speaks JSON there has to be a transformation
between PHP and JSON values. You can even write your own
custom mapping types that map PHP values or objects to JSON-able PHP
scalar or array values.

Here is a quick overview of the built-in mapping types:

-  ``string``: Type that maps a JSON String to a PHP string.
-  ``datetime``: Type that maps an stringified Date to a PHP
   DateTime object.
-  ``mixed``: Type that saves and retrives any value unconverted into the Couch.
-  ``integer``: Type that ensures the value to be an integer.
-  ``boolean``: Type that ensures the value to be a boolean.

Field Mapping
----------------

After a class has been marked as an entity it can specify mappings
for its instance fields. Here we will only look at simple fields
that hold scalar values like strings, numbers, etc. Associations to
other objects are covered in the chapter "Association Mapping".

To mark a property for relational persistence the ``@Field``
docblock annotation is used. This annotation usually requires at
least one attribute to be set, the ``type``. The ``type`` attribute
specifies the Doctrine Mapping Type to use for the field. If the
type is not specified, 'mixed' is used as the default mapping type
since it is the most flexible.

.. code-block:: php

    <?php
    /** @Document */
    class MyPersistentClass
    {
        /** @Field(type="string") */
        private $name;
    }

Indexing Fields
~~~~~~~~~~~~~~~

CouchDB uses map-reduce to query documents. Except querying for the ID of a document
there is no additional query capability available for fields. Doctrine CouchDB ODM
allows you to use a predefined view that allows equality comparisons on fields:

.. code-block:: php

    <?php
    /** @Document */
    class MyPersistentClass
    {
        /**
         * @Index
         * @Field(type="string")
         */
        private $name;
    }

All indexed fields can be queried in ``DocumentRepository::findBy()`` and ``DocumentRepository:findOneBy()``:

.. code-block:: php

    <?php
    $repository = $documentManager->getRepository("MyApp\Document\MyPersistentClass");
    $john = $repository->findOneBy(array("name" => "John Galt"));

Json Names
~~~~~~~~~~

If your fields for some reason have different names in the PHP class and CouchDB document you 
can use the attribute "jsonName" to specify the name of the key in the json document.

Id Mapping
----------

CouchDB documents have a special field "_id" that contains the globally
unique identifier of a document in the database. This is always a string,
so it suffices to specify only the @Id annotation on the property:

.. code-block:: php

    <?php
    /** @Document */
    class MyPersistentClass
    {
        /** @Id */
        private $id;
    }

Id Generation Strategies
~~~~~~~~~~~~~~~~~~~~~~~~

By default the ODM uses CouchDBs batch UUID generation mechanism
to generate a UUID for a document as soon as it is registered with
the DocumentManager. You can configure different strategies to generate
IDs, here is a list:

-   @Id(strategy="UUID") - Uses CouchDB UUID generation. This is the implicitly
    selected strategy if you do not specify the strategy argument
-   @Id(strategy="ASSIGNED") - Assumes that you as developer have assigned a unique
    identifier before passing the document to the DocumentManager for the first time.

Attachments
-----------

You can map an array of all CouchDB attachments to a document to a field in your PHP class:

.. code-block:: php

    <?php
    /** @Document */
    class MyPersistentClass
    {
        /** @Attachments */
        private $attachments;
    }

The mapped field is indexed by filename and contains instances of ``Doctrine\CouchDB\Attachment``.
Contents of the attachments are loaded lazily by using the stub details inside the CouchDB document.

Document Repositories
---------------------

A repository is a finder class for your documents. Every document automatically has a repository
of the type ``Doctrine\ODM\CouchDB\DocumentRepository``. You can specify your own repository classes
that extend the base repository and provide additional finder methods:

.. code-block:: php

    <?php
    /** @Document(repositoryClass="MyApp\Repository\MyPersistentRepository") */
    class MyPersistentClass
    {
        /** @Attachments */
        private $attachments;
    }

Then when calling ``DocumentManager#getRepository`` you will get an instance of your repository subclass:

.. code-block:: php

    <?php
    $repository = $documentManager->getRepository("MyApp\Document\MyPersistentClass");
    
