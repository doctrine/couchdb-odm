<?php

namespace Doctrine\Tests\Models\Embedded;

/**
 * @Document
 */
class EmbeddedReference {
    /**
     * @Id(strategy="ASSIGNED")
     */
    public $id;

    /**
     * @Field
     */
    protected $name;
    
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
        
        return $this;
    }    
}
