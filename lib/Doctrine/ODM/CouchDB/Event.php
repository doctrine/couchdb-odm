<?php

namespace Doctrine\ODM\CouchDB;

final class Event
{

    /**
     *
     * @var string
     */
    const onConflict = 'onConflict';

    /**
     *
     * @var string
     */
    const onFlush = 'onFlush';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const prePersist = 'prePersist';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const preRemove = 'preRemove';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const preUpdate = 'preUpdate';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const postRemove = 'postRemove';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const postUpdate = 'postUpdate';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const postLoad = 'postLoad';

    /**
     * This is an entity lifecycle event.
     *
     * @var string
     */
    const postPersist = 'postPersist';

    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct() {}
}
