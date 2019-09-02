<?php

namespace Doctrine\ODM\CouchDB;

final class Event
{
    const onConflict = 'onConflict';
    const onFlush = 'onFlush';
    const prePersist = 'prePersist';
    const preRemove = 'preRemove';
    const preUpdate = 'preUpdate';
    const postRemove = 'postRemove';
    const postUpdate = 'postUpdate';
    const postLoad = 'postLoad';

    private function __construct() {}
}
