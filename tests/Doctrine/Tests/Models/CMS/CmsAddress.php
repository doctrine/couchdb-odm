<?php

namespace Doctrine\Tests\Models\CMS;

class CmsAddress
{
    public $id;
    public $country;
    public $zip;
    public $city;
    public $street;
    public $user;

    public function getId() {
        return $this->id;
    }
    
    public function getUser() {
        return $this->user;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getZipCode() {
        return $this->zip;
    }

    public function getCity() {
        return $this->city;
    }
    
    public function setUser(CmsUser $user) {
        if ($this->user !== $user) {
            $this->user = $user;
            $user->setAddress($this);
        }
    }
}