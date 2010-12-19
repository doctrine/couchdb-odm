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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB;

use Doctrine\Common\Collections\Collection;

/**
 * Persistent collection class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
abstract class PersistentCollection implements Collection
{
    /** @var ArrayCollection */
    protected $col;
    protected $changed = false;

    abstract protected function load();

    public function changed()
    {
        return $this->changed;
    }

    public function takeSnapshot()
    {
        $this->changed = false;
    }

    public function unwrap()
    {
        return $this->col;
    }

    public function add($element)
    {
        $this->load();
        $this->changed = true;
        return $this->col->add($element);
    }

    public function clear()
    {
        $this->load();
        $this->changed = true;
        return $this->col->clear();
    }

    public function contains($element)
    {
        $this->load();
        return $this->col->contains($element);
    }

    public function containsKey($key)
    {
        $this->load();
        return $this->col->containsKey($key);
    }

    public function count()
    {
        $this->load();
        return $this->col->count();
    }

    public function current()
    {
        $this->load();
        return $this->col->current();
    }

    public function exists(Closure $p)
    {
        $this->load();
        return $this->col->exists($p);
    }

    public function filter(Closure $p)
    {
        $this->load();
        return $this->col->filter($p);
    }

    public function first()
    {
        $this->load();
        return $this->col->first();
    }

    public function forAll(Closure $p)
    {
        $this->load();
        return $this->col->forAll($p);
    }

    public function get($key)
    {
        $this->load();
        return $this->col->get($key);
    }

    public function getIterator()
    {
        $this->load();
        return $this->col->getIterator();
    }

    public function getKeys()
    {
        $this->load();
        return $this->col->getKeys();
    }

    public function getValues()
    {
        $this->load();
        return $this->col->getValues();
    }

    public function indexOf($element)
    {
        $this->load();
        return $this->col->indexOf($element);
    }

    public function isEmpty()
    {
        $this->load();
        return $this->col->isEmpty();
    }

    public function key()
    {
        $this->load();
        return $this->col->key();
    }

    public function last()
    {
        $this->load();
        return $this->col->last();
    }

    public function map(Closure $func)
    {
        $this->load();
        return $this->col->map($func);
    }

    public function next()
    {
        $this->load();
        return $this->col->next();
    }

    public function offsetExists($offset)
    {
        $this->load();
        return $this->col->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        $this->load();
        return $this->col->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->load();
        $this->changed = true;
        return $this->col->offsetSet($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->load();
        $this->changed = true;
        return $this->col->offsetUnset($offset);
    }

    public function partition(Closure $p)
    {
        $this->load();
        return $this->col->partition($p);
    }

    public function remove($key)
    {
        $this->load();
        $this->changed = true;
        return $this->col->remove($key);
    }

    public function removeElement($element)
    {
        $this->load();
        $this->changed = true;
        return $this->col->removeElement($element);
    }

    public function set($key, $value)
    {
        $this->load();
        $this->changed = true;
        return $this->col->set($key, $value);
    }

    public function slice($offset, $length = null)
    {
        $this->load();
        return $this->col->slice($offset, $length);
    }

    public function toArray()
    {
        $this->load();
        return $this->col->toArray();
    }
}
