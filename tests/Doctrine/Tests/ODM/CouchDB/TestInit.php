<?php

$basePath = __DIR__ . "/../../../../../";
$commonPath = $basePath . "lib/vendor/doctrine-common/lib/";

require_once $commonPath . "Doctrine/Common/ClassLoader.php";

$loader = new \Doctrine\Common\ClassLoader('Doctrine\Common', $commonPath);
$loader->register();

$loader = new \Doctrine\Common\ClassLoader('Doctrine\ODM\CouchDB', $basePath . "lib/");
$loader->register();

$loader = new \Doctrine\Common\ClassLoader('Doctrine\Tests', $basePath . "tests/");
$loader->register();
