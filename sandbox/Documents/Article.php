<?php

namespace Documents;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Document(indexed=true)
 */
class Article
{
    /** @Id(type="string") */
    protected $id;
    /** @Field(type="string") */
    protected $title;
    /** @Field(type="string", indexed=true) */
    protected $slug;
    /** @Field(type="string") */
    protected $body;
    /** @Field(type="datetime") */
    protected $created;
    /** @EmbedMany(targetDocument="Documents\Tag") */
    protected $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->created = new \DateTime("now");
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function addTag($tag)
    {
        $this->tags[] = new Tag($tag);
    }
}