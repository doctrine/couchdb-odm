# CouchDB ODM

[![Build Status](https://travis-ci.org/doctrine/couchdb-odm.png?branch=master)](https://travis-ci.org/doctrine/couchdb-odm)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/doctrine/couchdb-odm/badges/quality-score.png?s=dd7c75d0444b1386a17206f346d3910daa8d668b)](https://scrutinizer-ci.com/g/doctrine/couchdb-odm/)

Doctrine CouchDB is a mapper between PHP and CouchDB documents. It uses a metadata mapping
pattern to map the documents to plain old php objects, no ActiveRecord pattern or base class
of any kind is necessary.

Metadata mapping can be done through annotations, xml, yaml or php. A sample PHP object
that is mapped to CouchDB with annotations looks like this:

```php
/**
 * @Document
 */
class Article
{
    /** @Id */
    private $id;

    /**
     * @Field(type="string")
     */
    private $topic;

    /**
     * @Field(type="string")
     */
    private $text;

    /**
     * @ReferenceOne(targetDocument="User")
     */
    private $author;

    // a bunch of setters and getters
}
```

A simple workflow with this document looks like:

```php
<?php
$article = new Article();
$article->setTopic("Doctrine CouchDB");
$article->setText("Documentation");
$article->setAuthor(new Author("beberlei"));

// creating the document
$dm->persist($article);
$dm->flush();

$article = $dm->find("Article", 1234);
$article->setText("Documentation, and more documentation!");

// update the document
$dm->flush();

// removing the document
$dm->remove($article);
$dm->flush();
```

You can play around with the sandbox shipped in the sandbox/ folder of every git checkout
or read the documentation at http://docs.doctrine-project.org/projects/doctrine-couchdb/en/latest/

