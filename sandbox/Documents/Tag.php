<?php

namespace Documents;

/**
 * @EmbeddedDocument
 * @Document
 */
class Tag
{
    /**
     * @Id(strategy="ASSIGNED")
     * @var string
     */
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}