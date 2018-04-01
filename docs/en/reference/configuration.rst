Configuration
=============


Obtaining the DocumentManager
-----------------------------

To obtain the DocumentManager you have to start setting up a CouchDB configuration object.
See this example:

.. code-block:: php

    <?php
    $databaseName = "project_database_name";
    $documentPaths = array("MyApp\Documents");
    $httpClient = new \Doctrine\CouchDB\HTTP\SocketClient();
    $dbClient = new Doctrine\CouchDB\CouchDBClient($httpClient, $databaseName);

    $config = new \Doctrine\ODM\CouchDB\Configuration();
    $metadataDriver = $config->newDefaultAnnotationDriver($documentPaths);

    $config->setProxyDir(__DIR__ . "/proxies");
    $config->setMetadataDriverImpl($metadataDriver);
    $config->setLuceneHandlerName('_fti');

    $dm = new \Doctrine\ODM\CouchDB\DocumentManager($dbClient, $config);

CouchDBClient
-------------

You can create a CouchDBClient just by constructing a new instance.
The constructor requires an instantiated HTTP Client and a database name.

HTTP Client (***REQUIRED***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $client = new \Doctrine\CouchDB\HTTP\SocketClient();

There are two different HTTP Clients shipped with Doctrine CouchDB:

-   ``Doctrine\CouchDB\HTTP\SocketClient`` The default client uses fsockopen and
    has very good performance using keep alive connections.
-   ``Doctrine\CouchDB\HTTP\StreamClient`` Uses fopen and is therefore simpler than the SocketClient,
    however cannot use keep alive. In some PHP setups the SocketClient doesn't work and the StreamClient
    is a fallback for these situations.

You can pass the following arguments to configure the HTTP Client:

-   host (default: localhost)
-   port (default: 5984)
-   username (default: null)
-   password (default: null)
-   ip (default: null)

With the setOption Method you can change the additional options:

-  keep-alive (default: true)
-  timeout (default: 0.01)

Configuration Options
---------------------

The following sections describe all the configuration options
available on a ``Doctrine\ODM\CouchDB\Configuration`` instance.


Proxy Directory (***REQUIRED***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $config->setProxyDir($dir);
    $config->getProxyDir();

Gets or sets the directory where Doctrine generates any proxy
classes. For a detailed explanation on proxy classes and how they
are used in Doctrine, refer to the "Proxy Objects" section further
down.

Proxy Namespace (***OPTIONAL***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $config->setProxyNamespace($namespace);
    $config->getProxyNamespace();

Gets or sets the namespace to use for generated proxy classes. For
a detailed explanation on proxy classes and how they are used in
Doctrine, refer to the "Proxy Objects" section further down.

Metadata Driver (***REQUIRED***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $config->setMetadataDriverImpl($driver);
    $config->getMetadataDriverImpl();

Gets or sets the metadata driver implementation that is used by
Doctrine to acquire the object-relational metadata for your
classes.

There is currently one working available implementation:


-  ``Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver``

Throughout the most part of this manual the AnnotationDriver is
used in the examples. For information on the usage of the other drivers
please refer to the dedicated chapters.

The annotation driver can be configured with a factory method on
the ``Doctrine\ODM\CouchDB\Configuration``:

.. code-block:: php

    <?php
    $driverImpl = $config->newDefaultAnnotationDriver(array('/path/to/lib/MyApp/Documents'));
    $config->setMetadataDriverImpl($driverImpl);

The path information to the documents is required for the annotation
driver, because otherwise mass-operations on all entities through
the console could not work correctly. All of metadata drivers
accept either a single directory as a string or an array of
directories. With this feature a single driver can support multiple
directories of documents.

Metadata Cache (***RECOMMENDED***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $config->setMetadataCacheImpl($cache);
    $config->getMetadataCacheImpl();

Gets or sets the cache implementation to use for caching metadata
information, that is, all the information you supply via
annotations, xml or yaml, so that they do not need to be parsed and
loaded from scratch on every single request which is a waste of
resources. The cache implementation must implement the
``Doctrine\Common\Cache\Cache`` interface.

Usage of a metadata cache is highly recommended.

The recommended implementations for production are:


-  ``Doctrine\Common\Cache\ApcCache``
-  ``Doctrine\Common\Cache\MemcacheCache``
-  ``Doctrine\Common\Cache\XcacheCache``

For development you should use the
``Doctrine\Common\Cache\ArrayCache`` which only caches data on a
per-request basis.

Lucene Handler Name (***OPTIONAL***)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $config->setLuceneHandlerName($handlerName);
    $config->getLuceneHandlerName();

The default CouchDB Lucene handler is named "_fti", but it might be named differently in your
setup. You can rename this handler name with this option. You have to set this option
to "_fti", without setting this option it is supposed that CouchDB Lucene is not installed.

Proxy Objects
-------------

A proxy object is an object that is put in place or used instead of
the "real" object. A proxy object can add behavior to the object
being proxied without that object being aware of it. In Doctrine CouchDB,
proxy objects are used to realize several features but mainly for
transparent lazy-loading.

Proxy objects with their lazy-loading facilities help to keep the
subset of objects that are already in memory connected to the rest
of the objects. This is an essential property as without it there
would always be fragile partial objects at the outer edges of your
object graph.

Doctrine CouchDB implements a variant of the proxy pattern where it
generates classes that extend your document classes and adds
lazy-loading capabilities to them. Doctrine can then give you an
instance of such a proxy class whenever you request an object of
the class being proxied. This happens in two situations:

Reference Proxies
~~~~~~~~~~~~~~~~~

The method ``DocumentManager#getReference($documentName, $identifier)``
lets you obtain a reference to a document for which the identifier
is known, without loading that document from the database. This is
useful, for example, as a performance enhancement, when you want to
establish an association to a document for which you have the
identifier. You could simply do this:

.. code-block:: php

    <?php
    // $dm is an instance of DocumentManager
    // $cart is an instance of MyApp\Model\Cart
    // $itemId comes from somewhere, probably a request parameter
    $item = $dm->getReference('MyApp\Model\Item', $itemId);
    $cart->addItem($item);

Here, we added an Item to a Cart without loading the Item from the
database. If you invoke any method on the Item instance, it would
fully initialize its state transparently from the database. Here
$item is actually an instance of the proxy class that was generated
for the Item class but your code does not need to care. In fact it
**should not care**. Proxy objects should be transparent to your
code.

Association proxies
~~~~~~~~~~~~~~~~~~~

The second most important situation where Doctrine uses proxy
objects is when querying for objects. Whenever you query for an
object that has a single-valued association to another object that
is configured LAZY, without joining that association in the same
query, Doctrine puts proxy objects in place where normally the
associated object would be. Just like other proxies it will
transparently initialize itself on first access.
