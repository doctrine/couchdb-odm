<?php

namespace Doctrine\Tests\Models\CMS;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Document
 */
class CmsUser
{
    /** @Id */
    public $id;
    /** @String */
    public $status;
    /** @String */
    public $username;
    /** @String */
    public $name;
    public $phonenumbers;
    public $articles;
    public $address;
    public $groups;
    
    public function __construct() {
        $this->phonenumbers = new ArrayCollection;
        $this->articles = new ArrayCollection;
        $this->groups = new ArrayCollection;
    }

    public function getId() {
        return $this->id;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getName() {
        return $this->name;
    }

    /**
     * Adds a phonenumber to the user.
     *
     * @param CmsPhonenumber $phone
     */
    public function addPhonenumber(CmsPhonenumber $phone) {
        $this->phonenumbers[] = $phone;
        $phone->setUser($this);
    }

    public function getPhonenumbers() {
        return $this->phonenumbers;
    }

    public function addArticle(CmsArticle $article) {
        $this->articles[] = $article;
        $article->setAuthor($this);
    }

    public function addGroup(CmsGroup $group) {
        $this->groups[] = $group;
        $group->addUser($this);
    }

    public function getGroups() {
        return $this->groups;
    }

    public function removePhonenumber($index) {
        if (isset($this->phonenumbers[$index])) {
            $ph = $this->phonenumbers[$index];
            unset($this->phonenumbers[$index]);
            $ph->user = null;
            return true;
        }
        return false;
    }
    
    public function getAddress() { return $this->address; }
    
    public function setAddress(CmsAddress $address) {
        if ($this->address !== $address) {
            $this->address = $address;
            $address->setUser($this);
        }
    }
}
