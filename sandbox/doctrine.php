<?php

require_once getcwd() . "/cli-config.php";

$cli = new \Symfony\Component\Console\Application('Doctrine CouchDB CLI', Doctrine\ODM\CouchDB\Version::VERSION);
$cli->setHelperSet($helperSet);
$cli->addCommands(array(
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\ReplicationStartCommand(),
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\ReplicationCancelCommand(),
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\CompactDatabaseCommand(),
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\CompactViewCommand(),
    new \Doctrine\ODM\CouchDB\Tools\Console\Command\ViewCleanupCommand(),
));
$cli->run();
