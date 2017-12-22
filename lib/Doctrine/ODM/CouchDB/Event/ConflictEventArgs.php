<?php


namespace Doctrine\ODM\CouchDB\Event;

class ConflictEventArgs extends \Doctrine\Common\EventArgs
{
    private $data;
    private $documentManager;
    private $documentName;

    public function __construct($data, $documentManager, $documentName)
    {
        $this->data = $data;
        $this->documentManager = $documentManager;
        $this->documentName = $documentName;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return \Doctrine\ODM\CouchDB\DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }

    public function getDocumentName()
    {
        return $this->documentName;
    }
}
