<?php

namespace Doctrine\Tests\Models\CMS;

class CmsArticle
{
    public $id;
    public $topic;
    public $text;
    public $user;
    public $comments;
    public $version;
    
    public function setAuthor(CmsUser $author) {
        $this->user = $author;
    }

    public function addComment(CmsComment $comment) {
        $this->comments[] = $comment;
        $comment->setArticle($this);
    }
}
