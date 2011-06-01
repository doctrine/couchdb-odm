<?php

require_once getcwd() . "/cli-config.php";
xdebug_start_trace("/tmp/couch");
$cli = new \Symfony\Component\Console\Application('Doctrine CouchDB CLI', Doctrine\ODM\CouchDB\Version::VERSION);
$cli->setHelperSet($helperSet);
$cli->addCommands(array(
    new \Doctrine\CouchDB\Tools\Console\Command\ReplicationStartCommand(),
    new \Doctrine\CouchDB\Tools\Console\Command\ReplicationCancelCommand(),
    new \Doctrine\CouchDB\Tools\Console\Command\ViewCleanupCommand(),
    new \Doctrine\CouchDB\Tools\Console\Command\CompactDatabaseCommand(),
    new \Doctrine\CouchDB\Tools\Console\Command\CompactViewCommand(),
    new \Doctrine\CouchDB\Tools\Console\Command\MigrationCommand(),
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\UpdateDesignDocCommand(),
));
$cli->run();
