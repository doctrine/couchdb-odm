<?php

require_once __DIR__ . "/../lib/vendor/doctrine-common/lib/Doctrine/Common/ClassLoader.php";

$loader = new \Doctrine\Common\ClassLoader("Doctrine\Common", __DIR__ . "/../lib/vendor/doctrine-common/lib");
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("Doctrine", __DIR__ . "/../lib");
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("Symfony", __DIR__ . "/../lib/vendor");
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("Documents", __DIR__);
$loader->register();

$database = "doctrine_sandbox";

$httpClient = new \Doctrine\CouchDB\HTTP\SocketClient();

// create database if not existing
$resp = $httpClient->request('PUT', '/' . $database);

$reader = new \Doctrine\Common\Annotations\AnnotationReader();
$reader->setDefaultAnnotationNamespace('Doctrine\ODM\CouchDB\Mapping\\');
$paths = __DIR__ . "/Documents";
$metaDriver = new \Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver($reader, $paths);

$config = new \Doctrine\ODM\CouchDB\Configuration();
$config->setProxyDir(\sys_get_temp_dir());
$config->setMetadataDriverImpl($metaDriver);
$config->setLuceneHandlerName('_fti');

$couchClient = new \Doctrine\CouchDB\CouchDBClient($httpClient, $database);

$dm = \Doctrine\ODM\CouchDB\DocumentManager::create($couchClient, $config);

ob_start(function($output) {
    if (PHP_SAPI != "cli") {
        return nl2br($output);
    } else {
        return $output;
    }
});
