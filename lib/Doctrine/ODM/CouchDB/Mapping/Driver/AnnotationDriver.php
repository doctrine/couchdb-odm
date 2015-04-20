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

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader,
    Doctrine\Common\Annotations\AnnotationRegistry,
    Doctrine\Common\Annotations\Reader,
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver,
    Doctrine\ODM\CouchDB\Mapping\Annotations as ODM,
    Doctrine\ODM\CouchDB\Mapping\MappingException;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class AnnotationDriver extends AbstractAnnotationDriver
{
    protected $entityAnnotationClasses = array(
        'Doctrine\ODM\CouchDB\Mapping\Annotations\Document' => true,
        'Doctrine\ODM\CouchDB\Mapping\Annotations\EmbeddedDocument' => true,
        'Doctrine\ODM\CouchDB\Mapping\Annotations\MappedSuperclass' => true,
    );

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        $reflClass = $class->getReflectionClass();

        $isValidDocument  = false;
        $classAnnotations = $this->reader->getClassAnnotations($reflClass);

        foreach ($classAnnotations AS $classAnnotation) {
            if ($classAnnotation instanceof ODM\Document) {
                if ($classAnnotation->indexed) {
                    $class->indexed = true;
                }
                $class->setCustomRepositoryClass($classAnnotation->repositoryClass);
                $isValidDocument = true;
            } elseif ($classAnnotation instanceof ODM\EmbeddedDocument) {
                $class->isEmbeddedDocument = true;
                $isValidDocument = true;
            } else if ($classAnnotation instanceof ODM\MappedSuperclass) {
                $class->isMappedSuperclass = true;
                $isValidDocument = true;
            } else if ($classAnnotation instanceof ODM\Index) {
                $class->indexed = true;
            } else if ($classAnnotation instanceof ODM\InheritanceRoot) {
                $class->markInheritanceRoot();
            }
        }

        if ( ! $isValidDocument) {
            throw MappingException::classIsNotAValidDocument($className);
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($class->isInheritedAssociation($property->name) || $class->isInheritedField($property->name)) {
                continue;
            }

            $mapping = array();
            $mapping['fieldName'] = $property->name;

            if ($this->reader->getPropertyAnnotation($property, 'Doctrine\ODM\CouchDB\Mapping\Annotations\Index')) {
                $mapping['indexed'] = true;
            }

            foreach ($this->reader->getPropertyAnnotations($property) as $fieldAnnot) {
                if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Field) {
                    if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Version) {
                        $mapping['isVersionField'] = true;
                    }

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapField($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\ReferenceOne) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $mapping['cascade'] = $this->getCascadeMode($fieldAnnot->cascade);
                    unset($mapping['value']);
                    $class->mapManyToOne($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\ReferenceMany) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $mapping['cascade'] = $this->getCascadeMode($fieldAnnot->cascade);
                    unset($mapping['value']);
                    $class->mapManyToMany($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Attachments) {
                    $class->mapAttachments($mapping['fieldName']);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\EmbedOne || $fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\EmbedMany) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapEmbedded($mapping);
                }
            }
        }
    }

    /**
     * Gathers a list of cascade options found in the given cascade element.
     *
     * @param array $cascadeList cascade list
     * @return integer a bitmask of cascade options.
     * @throws MappingException
     */
    private function getCascadeMode(array $cascadeList)
    {
        $cascade = 0;
        foreach ($cascadeList as $cascadeMode) {
            $constantName = 'Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode);
            if (!defined($constantName)) {
                throw new MappingException("Cascade mode '$cascadeMode' not supported.");
            }
            $cascade |= constant($constantName);
        }
        return $cascade;
    }
}
