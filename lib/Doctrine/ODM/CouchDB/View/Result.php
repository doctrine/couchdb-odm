<?php

namespace Doctrine\ODM\CouchDB\View;

class Result implements \IteratorAggregate, \Countable, \ArrayAccess
{
    protected $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->result['rows']);
    }

    public function count()
    {
        return $this->result['limit'];
    }

    public function offsetExists($offset)
    {
        return isset($this->result['rows'][$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->result['rows'][$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException("LuceneResult is immutable and cannot be changed.");
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException("LuceneResult is immutable and cannot be changed.");
    }

    public function toArray()
    {
        return $this->result['rows'];
    }
}