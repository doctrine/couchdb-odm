Tools and Maintenance
=====================

CouchDB needs some maintenance now and then such as database and view compaction aswell as cleanups, The Doctrine CouchDB console command (based on Symfony Console) ships with a bunch of commands that help you with workflow.

If you are using Doctrine CouchDB via PEAR you can put a file called "cli-config.php" into your project root and register a Helper like such:

.. code-block:: php

    <?php

    require_once "bootstrap.php";

    $helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
        'couchdb' => new \Doctrine\CouchDB\Tools\Console\Helper\CouchDBHelper(null, $dm),
    ));

If you are using a downloadable or installation from git you should create your own executable for your project as shown in the sandbox or the bin/doctrine-couchdb.php script.

Now if you execute your script or the couchdb command installed with pear "doctrine-couchdb" you get the output of the help screen:

::

    shell> php doctrine-couchdb.php
    Doctrine CouchDB CLI version 1.0.0ALPHA2

    Usage:
      [options] command [arguments]

    Options:
      --help           -h Display this help message.
      --quiet          -q Do not output any message.
      --verbose        -v Increase verbosity of messages.
      --version        -V Display this program version.
      --ansi              Force ANSI output.
      --no-ansi           Disable ANSI output.
      --no-interaction -n Do not ask any interactive question.

    Available commands:
      help                                   Displays help for a command
      list                                   Lists commands
    couchdb
      couchdb:maintenance:compact-database   Compact the database
      couchdb:maintenance:compact-view       Compat the given view
      couchdb:maintenance:view-cleanup       Cleanup deleted views
      couchdb:migrate                        Execute a migration in CouchDB.
      couchdb:odm:update-design-doc          Update all new/modified registered design docs or a single document if a docname is provided.
      couchdb:replication:cancel             Cancel replication from a given source to target.
      couchdb:replication:start              Start replication from a given source to target.

couchdb:migrate
---------------

Pass this command an autoloadable class-name and it will execute a migration by paginating the _all_docs view in batches of 100 documents, either modifying the document to be in a new, migrated state or leaving it alone (return null).

An example of a migration is the Alpha1 to Alpha2 Migration class:

.. code-block:: php

    <?php
    namespace Doctrine\ODM\CouchDB\Tools\Migrations;

    use Doctrine\CouchDB\Tools\Migrations\AbstractMigration;

    class Alpha2Migration extends AbstractMigration
    {
        protected function migrate(array $docData)
        {
            $migrate = false;
            if (isset($docData['doctrine_metadata']['type'])) {
                $docData['type'] = $docData['doctrine_metadata']['type'];
                unset($docData['doctrine_metadata']['type']);
                $migrate = true;
            }
            if (isset($docData['doctrine_metadata']['associations'])) {
                $associations = array();
                foreach ($docData['doctrine_metadata']['associations'] AS $name => $values) {
                    $docData[$name] = $values;
                    $associations[] = $name;
                }
                $docData['doctrine_metadata'] = $associations;
                $migrate = true;
            }

            return ($migrate) ? $docData : null;
        }
    }

As you can see it migrates all doc.doctrine_metadata.type properties to doc.type and replaces the doc.doctrine_metadata.associations by moving the values into the main document just keeping a list of the association fields.

couchdb:maintenance:compact-database
------------------------------------

Will start a database compaction.

couchdb:maintenance:compact-view
------------------------------------

Will start a view compaction for the given design document.

couchdb:maintenance:view-cleanup
------------------------------------

Will cleanup leftover and temporary view files.

couchdb:odm:update-design-doc
-----------------------------

Will update the design document with the given name registered in `Doctrine\ODM\CouchDB\Configuration` to a new version. This is necessary to use when you change your design documents as Doctrine CouchDB cannot efficiently know if a view definition is outdated or not.
