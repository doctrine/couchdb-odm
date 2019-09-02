<?php


namespace Doctrine\ODM\CouchDB\Event;

class LifecycleEventArgs extends \Doctrine\Common\EventArgs
{
    private $document;

    private $documentManager;

    function __construct($document, $documentManager)
    {
        $this->document = $document;
        $this->documentManager = $documentManager;
    }

    /**
     * @return object
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @return \Doctrine\ODM\CouchDB\DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }
}
