<?php

require_once "bootstrap.php";

$article1 = new Documents\Article();
$article1->setTitle("Who is John Galt?");
$article1->setBody("Find out!");
$article1->addTag("Philosophy");

$article2 = new Documents\Article();
$article2->setTitle("Human Action");
$article2->setBody("Find out!");
$article2->addTag("Philosophy");
$article2->addTag("Economics");

$article3 = new Documents\Article();
$article3->setTitle("Design Patterns");
$article3->setBody("Find out!");
$article3->addTag("Computer Science");

$dm->persist($article1);
$dm->persist($article2);
$dm->persist($article3);
$dm->flush();
$dm->clear();