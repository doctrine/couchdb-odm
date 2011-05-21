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

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata,
    Doctrine\Common\Annotations\AnnotationReader,
    Doctrine\ODM\CouchDB\Mapping\MappingException;

// TODO: this is kinda ugly
require __DIR__ . '/DoctrineAnnotations.php';

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class AnnotationDriver implements Driver
{
    /**
     * The AnnotationReader.
     *
     * @var AnnotationReader
     */
    private $reader;

    /**
     * The paths where to look for mapping files.
     *
     * @var array
     */
    private $paths = array();

    /**
     * The file extension of mapping documents.
     *
     * @var string
     */
    private $fileExtension = '.php';

    /**
     * @param array
     */
    private $classNames;

    /**
     * Initializes a new AnnotationDriver that uses the given AnnotationReader for reading
     * docblock annotations.
     * 
     * @param $reader The AnnotationReader to use.
     * @param string|array $paths One or multiple paths where mapping classes can be found. 
     */
    public function __construct(AnnotationReader $reader, $paths = null)
    {
        $this->reader = $reader;
        if ($paths) {
            $this->addPaths((array) $paths);
        }
    }

    /**
     * Append lookup paths to metadata driver.
     *
     * @param array $paths
     */
    public function addPaths(array $paths)
    {
        $this->paths = array_unique(array_merge($this->paths, $paths));
    }

    /**
     * Retrieve the defined metadata lookup paths.
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        $reflClass = $class->getReflectionClass();

        $classAnnotations = $this->reader->getClassAnnotations($reflClass);
        if (isset($classAnnotations['Doctrine\ODM\CouchDB\Mapping\Document'])) {
            $documentAnnot = $classAnnotations['Doctrine\ODM\CouchDB\Mapping\Document'];

            if ($documentAnnot->indexed) {
                $class->indexed = true;
            }
            $class->setCustomRepositoryClass($documentAnnot->repositoryClass);
        } elseif (isset($classAnnotations['Doctrine\ODM\CouchDB\Mapping\EmbeddedDocument'])) {
            $documentAnnot = $classAnnotations['Doctrine\ODM\CouchDB\Mapping\EmbeddedDocument'];
            $class->isEmbeddedDocument = true;
        } else {
            throw MappingException::classIsNotAValidDocument($className);
        }

        foreach ($reflClass->getProperties() as $property) {
            $mapping = array();
            $mapping['fieldName'] = $property->getName();

            foreach ($this->reader->getPropertyAnnotations($property) as $fieldAnnot) {
                
                if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Field) {
                    if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Version) {
                        $mapping['isVersionField'] = true;
                    }

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapField($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\ReferenceOne) {
                    $cascade = 0;
                    foreach ($fieldAnnot->cascade AS $cascadeMode) {
                        $cascade += constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode));
                    }
                    $fieldAnnot->cascade = $cascade;

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapManyToOne($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\ReferenceMany) {
                    $cascade = 0;
                    foreach ($fieldAnnot->cascade AS $cascadeMode) {
                        $cascade += constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode));
                    }
                    $fieldAnnot->cascade = $cascade;

                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapManyToMany($mapping);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\Attachments) {
                    $class->mapAttachments($mapping['fieldName']);
                } else if ($fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\EmbedOne || $fieldAnnot instanceof \Doctrine\ODM\CouchDB\Mapping\EmbedMany) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    unset($mapping['value']);
                    $class->mapEmbedded($mapping);
                }
            }
        }
    }

    /**
     * Whether the class with the specified name is transient. Only non-transient
     * classes, that is entities and mapped superclasses, should have their metadata loaded.
     * A class is non-transient if it is annotated with either @Entity or
     * @MappedSuperclass in the class doc block.
     *
     * @param string $className
     * @return boolean
     */
    public function isTransient($className)
    {
        $classAnnotations = $this->reader->getClassAnnotations(new \ReflectionClass($className));

        return ! isset($classAnnotations['Doctrine\ODM\CouchDB\Mapping\Document']) &&
               ! isset($classAnnotations['Doctrine\ODM\CouchDB\Mapping\MappedSuperclass']) &&
               ! isset($classAnnotations['Doctrine\ODM\CouchDB\Mapping\EmbeddedDocument']);
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        if ($this->classNames !== null) {
            return $this->classNames;
        }

        if ( ! $this->paths) {
            throw MappingException::pathRequired();
        }

        $classes = array();
        $includedFiles = array();

        foreach ($this->paths as $path) {
            if ( ! is_dir($path)) {
                throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath();
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (($fileName = $file->getBasename($this->fileExtension)) == $file->getBasename()) {
                    continue;
                }

                $sourceFile = realpath($file->getPathName());
                require_once $sourceFile;
                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $rc = new \ReflectionClass($className);
            $sourceFile = $rc->getFileName();
            if (in_array($sourceFile, $includedFiles) && ! $this->isTransient($className)) {
                $classes[] = $className;
            }
        }

        $this->classNames = $classes;

        return $classes;
    }
}
