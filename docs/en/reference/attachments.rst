Attachments
===========

CouchDB supports attachments to documents and Docrine CouchDB ODM supports this aswell through
a simple API.

If you specify an attachments field for a document you can access all attachments
of a document of this type:

.. code-block:: php

    <?php
    /** @Document */
    class Article
    {
        /** @Attachments */
        private $attachments;
    }

The attachments field is an array of ``Doctrine\CouchDB\Attachment`` instances.
Keys of the attachments array are the filenames of the attachments.

The API of the ``Doctrine\CouchDB\Attachment`` looks as follows:

.. code-block:: php

    <?php
    namespace Doctrine\CouchDB;

    class Attachment
    {
        /**
         * Get the content-type of this attachment.
         *
         * Does not trigger lazy loading the attachment from the database.
         * 
         * @return string
         */
        public function getContentType();

        /**
         * Get the length of the base64 encoded representation of this attachment.
         *
         * Does not trigger lazy loading the attachment from the database.
         *
         * @return string
         */
        public function getLength();

        /**
         * Get the raw data of this attachment.
         *
         * @return string
         */
        public function getRawData();

        /**
         * @return string
         */
        public function getBase64EncodedData();

        /**
         * @return bool
         */
        public function isLoaded();

        /**
         * Number of times an attachment was already saved with the document, indicating in which revision it was added.
         *
         * @return int
         */
        public function getRevPos();

        /**
         * Attachments are special in how they need to be persisted depending on stub or not.
         *
         * TODO: Is this really necessary with all this special logic? Having attahments as special
         * case without special code would be really awesome.
         *
         * @return string
         */
        public function toArray();

        /**
         * Create an Attachment from a string or resource of binary data.
         *
         * WARNING: Changes to the file handle after calling this method will *NOT* be recognized anymore.
         *
         * @param string|resource $data
         * @param string $contentType
         * @return Attachment
         */
        static public function createFromBinaryData($data, $contentType = false);

        /**
         * Create an attachment from base64 data.
         *
         * @param string $data
         * @param string $contentType
         * @param int $revpos
         * @return Attachment
         */
        static public function createFromBase64Data($data, $contentType = false, $revpos = false);

        /**
         * Create a stub attachment that has lazy loading capabilities.
         *
         * @param string $contentType
         * @param int $length
         * @param int $revPos
         * @param Client $httpClient
         * @param string $path
         * @return Attachment
         */
        static public function createStub($contentType, $length, $revPos, Client $httpClient, $path);
    }

You have to use one of the two methods ``Attachment::createFromBinaryData()`` and
``Attachment::createFromBase64Data()`` to create new attachments. The key of the attachments
array will become the filename of this attachment.

.. code-block:: php

        <?php

        $fh = fopen(__DIR__ . '/_files/logo.jpg', 'r');

        $user = $dm->find('Doctrine\Tests\Models\CMS\CmsUser', 'user_with_attachment');
        $user->attachments['logo.jpg'] = \Doctrine\CouchDB\Attachment::createFromBinaryData($fh, 'image/jpeg');

        $dm->flush();

Attachments are value objects. If you want to replace an attachment with a new version just
replace the Attachment instance at the appropriate filename key.
