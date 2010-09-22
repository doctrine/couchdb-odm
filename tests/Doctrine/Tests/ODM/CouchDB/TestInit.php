<?php

$basePath = __DIR__ . "/../../../../../";
$commonPath = $basePath . "lib/vendor/doctrine-common/lib/";

require_once $commonPath . "Doctrine/Common/ClassLoader.php";

use Doctrine\Common\ClassLoader;

$classLoader = new ClassLoader('Doctrine\Common', $commonPath);
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\ODM\CouchDB', $basePath . "lib/");
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\Tests', $basePath . "tests/");
$classLoader->register();

$classLoader = new ClassLoader('Documents', __DIR__);
$classLoader->register();
