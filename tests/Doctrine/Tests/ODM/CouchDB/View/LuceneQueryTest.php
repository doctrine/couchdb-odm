<?php

namespace Doctrine\Tests\ODM\CouchDB\View;

class LuceneQueryTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    public function testExecute()
    {
        $jsonResult = <<<JSO
{
  "q": "+content:enron",
  "skip": 0,
  "limit": 2,
  "total_rows": 176852,
  "search_duration": 518,
  "fetch_duration": 4,
  "rows":   [
        {
      "id": "hain-m-all_documents-257.",
      "score": 1.601625680923462
    },
        {
      "id": "hain-m-notes_inbox-257.",
      "score": 1.601625680923462
    }
  ]
}
JSO;

        $doc = $this->getMock('Doctrine\CouchDB\View\DesignDocument');
        $doc->expects($this->once())
            ->method('getData')
            ->will($this->returnValue(array()));

        $client = $this->getMock('Doctrine\CouchDB\HTTP\Client', array(), array(), '', false);

        $client->expects($this->at(0))
               ->method('request')
               ->with($this->equalTo('GET'), $this->equalTo('/test/_fti/_design/test/test?'))
               ->will($this->returnValue(new \Doctrine\CouchDB\HTTP\ErrorResponse(404, array(), "", false)));

        $client->expects($this->at(1))
               ->method('request')
               ->with($this->equalTo('PUT'), $this->equalTo('/test/_design/test'))
               ->will($this->returnValue(new \Doctrine\CouchDB\HTTP\Response(201, array(), "", false)));

        $client->expects($this->at(2))
               ->method('request')
               ->with($this->equalTo('GET'), $this->equalTo('/test/_fti/_design/test/test?'))
               ->will($this->returnValue(new \Doctrine\CouchDB\HTTP\Response(200, array(), $jsonResult, false)));

        $query = new \Doctrine\CouchDB\View\LuceneQuery($client, 'test', '_fti', 'test', 'test', $doc);
        $result = $query->execute();

        $this->assertInstanceOf('Doctrine\CouchDB\View\LuceneResult', $result);

        $this->assertEquals(176852, $result->getTotalRows());
    }
}