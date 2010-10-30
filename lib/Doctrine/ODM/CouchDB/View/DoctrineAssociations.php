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

namespace Doctrine\ODM\CouchDB\View;

/**
 * Associations class
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineAssociations implements DesignDocument
{
    /**
     * Get view code
     *
     * Return the view code, which should be comitted to the database, which
     * should be structured like:
     *
     * <code>
     *  array(
     *      "name" => array(
     *          "map"     => "code",
     *          ["reduce" => "code"],
     *      ),
     *      ...
     *  )
     * </code>
     */
    public function getViews()
    {
        $mapInverseAssociations = <<<'JS'
function (doc)
{
    if (doc.doctrine_metadata
        && doc.doctrine_metadata.associations
    ) {
        var associations = doc.doctrine_metadata.associations;
        for ( type in associations ) {
            if (associations[type] != null) {
                if (typeof associations[type] == 'object') {
                    for ( var i = 0; i < associations[type].length; ++i ) {
                        emit([associations[type][i], type, doc._id], {"_id": doc._id} );
                    }
                } else {
                    emit([associations[type], type, doc._id], {"_id": doc._id} );
                }
            }
        }
    }
}
JS;

        return array(
            'inverse_associations' => array(
                'map' => $mapInverseAssociations,
            ),
        );
    }
}
