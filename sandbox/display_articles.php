<?php

require_once "bootstrap.php";

$article = new Documents\Article();
$article->setTitle("Who is John Galt?");
$article->setBody("Find out!");
$article->addTag("Philosophy");

$dm->persist($article);
$dm->flush();
$dm->clear();

$articles = $dm->getRepository('Documents\Article')->findAll();

foreach ($articles AS $article) {
    echo "ID: " . $article->getId() . "\n";
    echo "Title: " . $article->getTitle() . ", " . $article->getCreated()->format('d.m.Y, H:i') . "\n";
    echo "Tags: \n";
    foreach ($article->getTags() AS $tag) {
        echo "  - " . $tag->getName() . "\n";
    }
    echo "\n";
}
