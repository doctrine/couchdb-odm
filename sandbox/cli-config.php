<?php

require_once "bootstrap.php";

$helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
    'couchdb' => new \Doctrine\CouchDB\Tools\Console\Helper\CouchDBHelper(null, $dm),
));