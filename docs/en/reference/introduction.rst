Introduction
============

Doctrine CouchDB comes with two components that enable you to communicate to a CouchDB database using PHP:

-   **CouchDB Client** - A thin layer that includes a HTTP clients optimized for speaking to CouchDB and a convenience wrapper around the CouchDB API.
-   **Object-Document-Mapper (ODM)** - A mapping layer that maps CouchDB documents to PHP objects following the Doctrine persistence semantics also employed by the Doctrine 2.0 ORM and MongoDB ODM.

You can use the CouchDB Client without the Document-Mapper if you just want to talk to a CouchDB database without the object mapping. The Mapper however depends on the CouchDB Client.

Features
--------

-   Convenience layer to access CouchDB HTTP Document, Bulk and View API
-   Mapping layer for CouchDB Documents to PHP Objects
-   Write-behind changes from PHP Memory to CouchDB for high-performance
-   Map CouchDB views to PHP objects
-   Integration into CouchDB Lucene to query Lucene and retrieve your mapped PHP objects.
-   Explicit support for document conflict resolution

Architecture
------------

The Doctrine persistence semantics demand a strict separation of persistence and business logic. This means the PHP Objects that you can map to CouchDB documents do not extend any base class or implement any interface. These objects and their associations between each other don't even know that they are saved inside the CouchDB, for all they know it could also be the ORM or MongoDB solving their persistence needs. Instead of having ``save()``, ``delete()`` and finder methods on your PHP objects Doctrine CouchDB ODM provides you with a management/query layer that translates between CouchDB documents and PHP objects.

For example take this BlogPost entity:

.. code-block:: php

    <?php
    namespace MyApp\Document;

    class BlogPost
    {
        private $id;
        private $headline;
        private $text;
        private $publishDate;
        
        // getter/setter here
    }

No abstract/base-class nor interface was implemented, yet you can save an object of ``BlogPost`` into the CouchDB. You have to provide some metadata mapping information though, which is a necessary evil of any generic solution mapping between two different systems (CouchDB and PHP Objects). Doctrine CouchDB allows you to specifiy metadata using four different means: PHP Docblock Annotations, YAML, XML and PHP. Have a look at the following code examples:

.. configuration-block::

    .. code-block:: php

        <?php
        namespace MyApp\Document;
        
        /** @Document */
        class BlogPost
        {
            /** @Id */
            private $id;
            /** @Field(type="string") */
            private $headline;
            /** @Field(type="string") */
            private $text;
            /** @Field(type="datetime") */
            private $publishDate;
            
            // getter/setter here
        }

    .. code-block:: xml
    
        <doctrine-mapping>
            <document name="MyApp\Document\BlogPost">
                <id name="id" />
                <field name="headline" type="string" />
                <field name="text" type="string" />
                <field name="publishDate" type="datetime" />
            </document>
        </doctrine-mapping>

    .. code-block:: yaml

        MyApp\Document\BlogPost:
            type: document
            id:
                id: ~
            fields:
                headline: { type: string }
                text: { type: string }
                publishDate: { type: datetime }

These simple definitions describe to Doctrine CouchDB ODM what parts of your object should be mapped. Now your application code saving an instance of a blog post will look like the following lines:

.. code-block:: php

    <?php
    // $dm is an instance of Doctrine\ODM\CouchDB\DocumentManager

    use MyApp\Document\BlogPost;

    $blogPost = new BlogPost();
    $blogPost->setHeadline("Hello World!");
    $blogPost->setText("This is a blog post going to be saved into CouchDB");
    $blogPost->setPublishDate(new \DateTime("now"));

    $dm->persist($blogPost);
    $dm->flush();

Using an instance of a ``DocumentManager`` you can save the blog post into your CouchDB instance. Calling ``persist`` tells the DocumentManager that this blog post is new and should be managed by Doctrine CouchDB ODM. It does not actually perform a POST request to create a document yet. The only thing that is being done is the generation of a UUID, which is automatically injected into the ``$id`` variable of your BlogPost.

The call to ``flush()`` triggers the synchronization of the in-memory state of all current objects with the CouchDB database. In this particular case only one BlogPost is managed by the DocumentManager and does not yet have a corresponding document in the CouchDB, hence a POST request to create the document is executed. 

Write-Behind
~~~~~~~~~~~~

You may ask yourself why ``persist`` and ``flush`` are two separate functions in the previous example. Doctrine persistence semantics apply a performance optimization technique by aggregating all the required changes and synchronizing them back to the database at once. In the case of CouchDB ODM this means that all changes on objects (managed by CouchDB) in memory of the current PHP request are synchronized to CouchDB in a single POST request using the HTTP Bulk Document API. Compared to making an update request per document this leads to a considerable increase in performance.

This approach has a drawback though with regards to the transactional semantics of CouchDB. By default the bulk update is forced using the allOrNothing parameter of the HTTP Bulk Document API, which means that in case of different versioning numbers it will produce document conflicts that you have to resolve later. Doctrine CouchDB ODM offers an event to resolve any document conflict and it is planned to offer automatic resolution strategies such as "First-One Wins" or "Last-One Wins". If you don't enable forcing changes to the CouchDB you can end up with inconsistent state, for example if one update of a document is accepted and another one is rejected.

We haven't actually figured out the best way of handling "object transactions" ourselfes, but are experimenting with it to find the best possible solution before releasing a stable Doctrine CouchDB version. Feedback in this area is highly appreciated.

Querying
~~~~~~~~

Coming back to our blog post example, in any next request you can grab the BlogPost by calling a simple finder method:

.. code-block:: php

    <?php
    // $dm is an instance of Doctrine\ODM\CouchDB\DocumentManager
    $blogPost = $dm->find("MyApp\Document\BlogPost", $theUUID);

Here the variable ``$blogPost`` will be of the type ``MyApp\Document\BlogPost`` with no magic whatsoever being attached to that object. There will be some magic required later on described in the Object-Graph Traversal section, but its the most unspectacular magic we could come up with.

If you know how CouchDB works you are probably now asking yourself how the HTTP View API of CouchDB is integrated into Doctrine CouchDB ODM to offer convenience finder methods such as MongoDB or a relational database would easily allow. The answer may shock you: There is no magic query API provided except the previously shown query by ID (UUID or any assigned document ID) by default. You have to actively mark fields as "indexed" to be able to access them using equality constraints.

The reason for this approach is easily explained. While Doctrine CouchDB could potentially generate a bunch of powerful views for you that allow querying all fields by different means could potentially lead to a performance problem for your application down the road. Views in CouchDB come at a price: The more there are the slower your CouchDB gets. A view that indexes all fields of all documents would be much too large, so you have to construct the views yourself or use the indexing attributes to generate a simple query.

Object-Graph Traversal
~~~~~~~~~~~~~~~~~~~~~~

Besides the actual saving of objects the Doctrine persistence semantics dictate that you can always traverse from any part of the object graph to any other part as long as there are associations connecting the objects with each other. In a simple implementation this would mean you have to load all the objects into memory for every request. However Doctrine CouchDB is very efficient using the lazy-loading pattern.

Every single or multi-valued association such as Many-To-One or Many-To-Many are replaced with lazy loading proxies when created. This means the object graph is fully traversable, but only the parts you actually access are loaded into memory. For this feature to work there is some code-generation necessary, Doctrine creates proxy classes that extend your documents if necessary.

Why NoSQL?
----------

Document databases map perfectly to objects (in any language, not just PHP). The Doctrine ORM in contrast requires very complex logic to allow the translation from relational databasees to PHP objects and back and still lacks a lot of the features that NoSQL databases allow:

-   Assocations between arbitrary collections of objects
-   Embedded (value) objects
-   Saving (associative) arrays of data or references.

This is all possible without much hazzle in CouchDB. However there are also downsides:

-   Migrations between different versions of the same document type are complicated
-   No transactional support
-   No foreign key support

This is only a very small overview of the differences between the ORM and CouchDB ODM. There are up- and downsides in using both of them and you should pick the one that suits your needs best.

CouchDB Document Structure
--------------------------

Doctrine maps keys of CouchDB documents to PHP object properties. Take the following sample CouchDB document:

.. code-block:: javascript

    {
       "_id": "2a9d3e2af0797fad094dded89a61c18b",
       "_rev": "1-e76c463b527734b80f9ba55965fdffdf",
       "name": "John Doe",
       "country": "New Zealand"
    }

It would make sense to map this document to a PHP object called "Person" and Doctrine could populate it the following way:

.. code-block:: php

    <?php
    namespace MyApp\Document;

    /** @Document */
    class Person
    {
        /** @Id */
        public $id;
        /** @Field(type="string") */
        public $name;
        /** @Field(type="string") */
        public $country;
    }

    $person = new \MyApp\Document\Person();
    $person->id = "2a9d3e2af0797fad094dded89a61c18b";
    $person->name = "John Doe";
    $person->country = "New Zealand";

This is basically what Doctrine CouchDB does, but it does more under the hood.

Document Type
~~~~~~~~~~~~~

In the following API call to the ``DocumentManager`` not only the objects ID in CouchDB is given to the find method, but the type of the class aswell.

.. code-block:: php

    <?php
    $person = $dm->find("Person", "2a9d3e2af0797fad094dded89a61c18b");

Because CouchDB works with globally unique identifiers on the database level this restriction is not necessary technically, but there are three reasons why Doctrine CouchDB enforces them:

-   MongoDB and the ORM need the type of object to determine which set of identifiers to query for the given identifier. MongoDB saves documents in different collections, the ORM saves the objects in different database tables. Doctrine uses a unified set of Persistence interfaces and CouchDB has to follow them.
-   The given type is used to make an assertion if the document found in CouchDB is really of the specified type. This helps to force some structure onto the schemaless design of CouchDB and will help to assure your code always works with the right set of objects.
-   This is also a security mechanism, it automatically prevents users from instantiating documents of different types by changing the url of a page.

To make this assertion work Doctrine CouchDB has to save the type of the document aswell. This is done in a special metadata key "type" inside the CouchDB documents:

.. code-block:: javascript

    {
        "_id": "2a9d3e2af0797fad094dded89a61c18b",
        "_rev": "1-e76c463b527734b80f9ba55965fdffdf",
        "type": "MyApp.Document.Person",
        "name": "John Doe",
        "country": "New Zealand"
    }

The namespace seperator is translated into a dot to simplify using this information in CouchDB views, because the PHP namespace seperator needs to be escaped in javascript literals.

Associations
~~~~~~~~~~~~

By default CouchDB does not support associations. Of course you can just save associated identifiers in a document key, but CouchDB cannot enforce referential integrity for this associations. If the referenced document is deleted you will have a dangling reference to it. You have to be aware of this potential problem when developing an application with Doctrine CouchDB.

On top of saving the association reference id into a matching json document key, Doctrine CouchDB uses a special associations key in the doctrine_metadata key to save the field names of associations. Using this mechanism Doctrine can use a single generic view to make all associations accessible from the "inverse side" of their relationship. Lets extend our example of the "Person" class, which shall now have a reference to a set of addresses and to their mother and father:

.. code-block:: javascript

    {
        "_id": "2a9d3e2af0797fad094dded89a61c18b",
        "_rev": "1-e76c463b527734b80f9ba55965fdffdf",
        "type": "MyApp.Document.Person",
        "doctrine_metadata":
        {
            "associations": ["father", "mother", "addresses"]
        }
        "name": "John Doe",
        "country": "New Zealand",
        "father": "4cb8afdfdafdacbfbabf02575d210e3f",
        "mother": "884eeb55df405b43d03a5474f4371f98",
        "addresses":
        {
            "4e0f9cc999cbd2694d6dd5cc37f6ee47",
            "5091720c6b040e15eea38b46747ae0ab"
        }
    }

The associated php object looks like:

.. code-block:: php

    <?php
    /** @Document */
    class Person
    {
        /** @Id */
        public $id;
        /** @Field(type="string") */
        public $name;
        /** @Field(type="string") */
        public $country;
        /** @ReferenceOne(targetDocument="Person") */
        public $father;
        /** @ReferenceOne(targetDocument="Person") */
        public $mother;
        /** @ReferenceMany(targetDocument="Address") */
        public $addresses;
    }

In this example the @ReferenceOne and @ReferenceMany annotations, which are used to register associations with the CouchDB mapper, are restricted to target documents of certain types (Person and Address). You can aswell omit this information and CouchDB will allow you to save arbitrary objects into this field.

Indexes
~~~~~~~

The Doctrine persistence interfaces ship with a concept called ObjectRepository that allows to query for any one or set of fields of an object. Because CouchDB uses views for querying (comparable to materialized views in relational databases) this functionality cannot be achieved out of the box. Doctrine CouchDB could offer a view that exposes every field of every document, but this view would only grow into infinite size and most of the information would be useless.

Instead you have to explicitly set classes and fields as "indexed", which will then allow to query them through the ObjectRepository finder methods:

.. code-block:: php

    <?php
    /** @Document(indexed=true) */
    class Person
    {
        /**
         * @Index
         * @Field(type="string")
         */
        public $name;
    }

This will lead to a JSON document structure that looks like:

.. code-block:: javascript

    {
        "_id": "2a9d3e2af0797fad094dded89a61c18b",
        "_rev": "1-e76c463b527734b80f9ba55965fdffdf",
        "type": "MyApp.Document.Person",
        "doctrine_metadata":
        {
            "indexed": true,
            "indexes": ["name"]
        },
        "name": "John Doe",
        "country": "New Zealand"
    }

You can now query the repository for person objects:

.. code-block:: php

    <?php
    // enabled with @Document(indexed=true)
    $persons = $dm->getRepository('MyApp\Document\Person')->findAll();
    // enabled with @Index on $name property
    $persons = $dm->getRepository('MyApp\Document\Person')->findBy(array("name" => "Benjamin"));

All this functionality is described in detail in later chapters, this chapter served as introduction how Doctrine saves its data into CouchDB documents.
