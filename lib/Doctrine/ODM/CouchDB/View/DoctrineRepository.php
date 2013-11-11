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


namespace Doctrine\ODM\CouchDB\View;

use Doctrine\CouchDB\View\DesignDocument;

/**
 * Repository queries
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineRepository implements DesignDocument
{
    public function getData()
    {
        $mapRepositoryEqualConstraint = <<<'JS'
function (doc)
{
    if (doc.doctrine_metadata
        && doc.doctrine_metadata.indexes
    ) {
        var indexes = doc.doctrine_metadata.indexes;
        for ( idx in indexes ) {
            if (doc[indexes[idx]] != null) {
                emit([doc.type, indexes[idx], doc[indexes[idx]]], {"_id": doc._id} );
            }
        }
    }
}
JS;

        $mapRepositoryTypeConstraint = <<<'JS'
function (doc)
{
    if (doc.type
        && doc.doctrine_metadata
        && doc.doctrine_metadata.indexed) {
        emit(doc.type, {"_id": doc._id} );
    }
}
JS;

        return array(
            'views' => array(
                'equal_constraint' => array(
                    'map' => $mapRepositoryEqualConstraint,
                ),
                'type_constraint' => array(
                    'map' => $mapRepositoryTypeConstraint,
                ),
            )
        );
    }
}
