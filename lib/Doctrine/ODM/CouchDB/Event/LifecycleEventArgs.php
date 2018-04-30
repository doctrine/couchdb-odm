<?php

namespace Doctrine\ODM\CouchDB\Event;

use Doctrine\Common\EventArgs;

class LifecycleEventArgs extends EventArgs
{
    private $document;

    private $documentManager;

    public function __construct($document, $documentManager)
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
