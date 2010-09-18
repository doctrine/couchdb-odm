<?php

$commonPath = __DIR__ . "./../../../../../lib/vendor/doctrine-common/lib/";

require_once $commonPath . "Doctrine/Common/ClassLoader.php";

$loader = new \Doctrine\Common\ClassLoader('Doctrine\Common', $commonPath);
$loader->register();

$loader = new \Doctrine\Common\ClassLoader('Doctrine\ODM\CouchDB', __DIR__ . "/../../../../lib/");
$loader->register();
