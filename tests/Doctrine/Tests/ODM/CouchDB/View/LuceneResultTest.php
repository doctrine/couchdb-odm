<?php

namespace Doctrine\Tests\ODM\CouchDB\View;

use Doctrine\CouchDB\View\LuceneResult;

class LuceneResultTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    public function testConstruction()
    {
        $result = new LuceneResult(array(
            "etag" => "asdf",
            "fetch_duration" => 1234,
            "limit" => 20,
            "q" => "asdf + foo",
            "rows" => array(
                array("id" => "bar", "score" => 1234),
                array("id" => "foo", "score" => 4321),
            ),
            "search_duration" => 4321,
            "skip" => 20,
            "total_rows" => 9999,
        ));

        $this->assertEquals("asdf", $result->getETag());
        $this->assertEquals(1234, $result->getFetchDuration());
        $this->assertEquals(20, $result->getLimit());
        $this->assertEquals("asdf + foo", $result->getExecutedQuery());
        $this->assertEquals(4321, $result->getSearchDuration());
        $this->assertEquals(20, $result->getSkip());
        $this->assertEquals(9999, $result->getTotalRows());

        $this->assertEquals(array(
                array("id" => "bar", "score" => 1234),
                array("id" => "foo", "score" => 4321),
            ), $result->getRows());
    }

    public function testGetRowsIterator()
    {
        $result = new LuceneResult(array(
            "rows" => array(
                array("id" => "foo", "score" => 1234),
                array("id" => "foo", "score" => 1234),
            ),
        ));

        $i = 0;
        foreach ($result AS $row) {
            $this->assertEquals("foo", $row['id']);
            $this->assertEquals(1234, $row['score']);
            $i++;
        }
        $this->assertEquals(2, $i);
    }

    public function testCountable()
    {
        $result = new LuceneResult(array('limit' => 1, "rows" => array(0 => array("_id" => "foo", "score" => 1234))));

        $this->assertEquals(1, count($result));
    }

    public function testArrayAccessForRows()
    {
        $result = new LuceneResult(array(
            "rows" => array(
                array("id" => "foo", "score" => 1234),
                array("id" => "foo", "score" => 1234),
            ),
        ));

        $this->assertEquals("foo", $result[0]['id']);
        $this->assertEquals("foo", $result[1]['id']);
        $this->assertEquals(1234, $result[0]['score']);
        $this->assertEquals(1234, $result[1]['score']);
    }
}