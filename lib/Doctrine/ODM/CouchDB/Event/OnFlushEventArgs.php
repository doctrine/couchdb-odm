<?php


namespace Doctrine\ODM\CouchDB\Event;

class OnFlushEventArgs extends \Doctrine\Common\EventArgs
{
    private $documentManager;

    public function __construct($documentManager)
    {
        $this->documentManager = $documentManager;
    }

    /**
     * @return \Doctrine\ODM\CouchDB\DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }
}
