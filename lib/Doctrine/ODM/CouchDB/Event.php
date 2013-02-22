<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

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
