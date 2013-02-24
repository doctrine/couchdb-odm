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
    Doctrine\ODM\CouchDB\Mapping\ClassMetadata as ClassMetaDataInfo,
    Doctrine\ODM\CouchDB\Mapping\MappingException,
    Doctrine\ODM\CouchDB\Event;

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
        'Doctrine\\ODM\\CouchDB\\Mapping\\Annotations\\Document' => 1,
        'Doctrine\\ODM\\CouchDB\\Mapping\\Annotations\\MappedSuperclass' => 2,
        'Doctrine\\ODM\\CouchDB\\Mapping\\Annotations\\EmbeddedDocument' => 3,
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

            if ($classAnnotation instanceof ODM\HasLifecycleCallbacks) {

                /* @var $method \ReflectionMethod */
                foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

                    // filter for the declaring class only, callbacks from parents will already be registered.
                    if ($method->getDeclaringClass()->name !== $reflClass->name) {
                        continue;
                    }

                    foreach ($this->getMethodCallbacks($method) as $value) {
                        $class->addLifecycleCallback($value[0], $value[1]);
                    }
                }

            }
        }

        if ( ! $isValidDocument) {
            throw MappingException::classIsNotAValidDocument($className);
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($class->isInheritedAssociation($property->name) || $class->isInheritedField($property->name)) {
                continue;
            }

            $isField = false;
            $mapping = array();
            $mapping['fieldName'] = $property->name;

            foreach ($this->reader->getPropertyAnnotations($property) as $fieldAnnot) {
                if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Field) {
                    if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Version) {
                        $mapping['isVersionField'] = true;
                    }

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $isField = true;
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\Index) {
                    $mapping['indexed'] = true;
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\ReferenceOne) {
                    $cascade = 0;
                    foreach ($fieldAnnot->cascade AS $cascadeMode) {
                        $cascade += constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode));
                    }
                    $fieldAnnot->cascade = $cascade;

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapManyToOne($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\ReferenceMany) {
                    $cascade = 0;
                    foreach ($fieldAnnot->cascade AS $cascadeMode) {
                        $cascade += constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode));
                    }
                    $fieldAnnot->cascade = $cascade;

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
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

            if ($isField) {
                $class->mapField($mapping);
            }
        }

    }

    /**
     * Parses the given method.
     *
     * @param \ReflectionMethod $method
     *
     * @return array
     */
    private function getMethodCallbacks(\ReflectionMethod $method)
    {
        $callbacks   = array();
        $annotations = $this->reader->getMethodAnnotations($method);

        foreach ($annotations as $annot) {
            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PrePersist) {
                $callbacks[] = array($method->name, Event::prePersist);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PostPersist) {
                $callbacks[] = array($method->name, Event::postPersist);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PreUpdate) {
                $callbacks[] = array($method->name, Event::preUpdate);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PostUpdate) {
                $callbacks[] = array($method->name, Event::postUpdate);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PreRemove) {
                $callbacks[] = array($method->name, Event::preRemove);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PostRemove) {
                $callbacks[] = array($method->name, Event::postRemove);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PostLoad) {
                $callbacks[] = array($method->name, Event::postLoad);
            }

            if ($annot instanceof \Doctrine\ODM\CouchDB\Mapping\Annotations\PreFlush) {
                $callbacks[] = array($method->name, Event::preFlush);
            }
        }

        return $callbacks;
    }

}
