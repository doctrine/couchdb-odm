<?php

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader,
    Doctrine\Common\Annotations\AnnotationRegistry,
    Doctrine\Common\Annotations\Reader,
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver,
    Doctrine\ODM\CouchDB\Mapping\Annotations as ODM,
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
