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

namespace Doctrine\ODM\CouchDB\Proxy;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\Common\Proxy\Exception\UnexpectedValueException;
use ReflectionProperty;

/**
 * This factory is used to create proxy objects for entities at runtime.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
 * @author Nils Adermann  <naderman@naderman.de>
 * @author Marco Pivetta  <ocramius@gmail.com>
 *
 * This whole thing is copy & pasted from ORM - should really be slightly
 * refactored to generate
 */
class ProxyFactory
{
    /**
     * @var \Doctrine\ODM\CouchDB\DocumentManager The DocumentManager this factory is bound to.
     */
    private $dm;

    /**
     * @var \Doctrine\Common\Proxy\ProxyGenerator
     */
    private $proxyGenerator;

    /**
     * @var bool Whether to automatically (re)generate proxy classes.
     */
    private $autoGenerate;
    /**
     * @var string The namespace that contains all proxy classes.
     */
    private $proxyNamespace;

    /**
     * @var string The directory that contains all proxy classes.
     */
    private $proxyDir;

    /**
     * @var array definitions (indexed by requested class name) for the proxy classes.
     *            Each element is an array containing following items:
     *            "fqcn"         - FQCN of the proxy class
     *            "initializer"  - Closure to be used as proxy __initializer__
     *            "cloner"       - Closure to be used as proxy __cloner__
     *            "reflectionId" - ReflectionProperty for the ID field
     */
    private $definitions = array();

    /**
     * Initializes a new instance of the <tt>ProxyFactory</tt> class that is
     * connected to the given <tt>DocumentManager</tt>.
     *
     * @param \Doctrine\ODM\CouchDB\DocumentManager $dm The DocumentManager the new factory works for.
     * @param string $proxyDir The directory to use for the proxy classes. It must exist.
     * @param string $proxyNs The namespace to use for the proxy classes.
     * @param boolean $autoGenerate Whether to automatically generate proxy classes.
     */
    public function __construct(DocumentManager $dm, $proxyDir, $proxyNs, $autoGenerate = false)
    {
        $this->dm             = $dm;
        $this->proxyDir       = $proxyDir;
        $this->autoGenerate   = $autoGenerate;
        $this->proxyNamespace = $proxyNs;
    }

    /**
     * Gets a reference proxy instance for the document of the given type and identified by
     * the given identifier.
     *
     * @param  string $className
     * @param  mixed  $identifier
     * @return object
     */
    public function getProxy($className, $identifier)
    {
        if ( ! isset($this->definitions[$className])) {
            $this->initProxyDefinitions($className);
        }

        $definition   = $this->definitions[$className];
        $fqcn         = $definition['fqcn'];
        $reflectionId = $definition['reflectionId'];
        $proxy        = new $fqcn($definition['initializer'], $definition['cloner']);

        $reflectionId->setValue($proxy, $identifier);

        return $proxy;
    }

    /**
     * Generates proxy classes for all given classes.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata[] $classes The classes for which to generate proxies.
     * @param string $toDir The target directory of the proxy classes. If not specified, the
     *                      directory configured on the Configuration of the DocumentManager used
     *                      by this factory is used.
     * @return int Number of generated proxies.
     */
    public function generateProxyClasses(array $classes, $toDir = null)
    {
        $generated = 0;

        foreach ($classes as $class) {
            if ($class->isMappedSuperclass || $class->getReflectionClass()->isAbstract()) {
                continue;
            }

            $generator     = $this->getProxyGenerator();
            $proxyFileName = $generator->getProxyFileName($class->getName(), $toDir);

            $generator->generateProxyClass($class, $proxyFileName);

            $generated += 1;
        }

        return $generated;
    }

    /**
     * @param \Doctrine\Common\Proxy\ProxyGenerator $proxyGenerator
     */
    public function setProxyGenerator(ProxyGenerator $proxyGenerator)
    {
        $this->proxyGenerator = $proxyGenerator;
    }

    /**
     * @return \Doctrine\Common\Proxy\ProxyGenerator
     */
    public function getProxyGenerator()
    {
        if (null === $this->proxyGenerator) {
            $this->proxyGenerator = new ProxyGenerator($this->proxyDir, $this->proxyNamespace);

            $this->proxyGenerator->setPlaceholder('<baseProxyInterface>', 'Doctrine\ODM\CouchDB\Proxy\Proxy');
        }

        return $this->proxyGenerator;
    }

    /**
     * @param string $className
     */
    private function initProxyDefinitions($className)
    {
        $fqcn = ClassUtils::generateProxyClassName($className, $this->proxyNamespace);
        $classMetadata = $this->dm->getClassMetadata($className);

        if ( ! class_exists($fqcn, false)) {
            $generator = $this->getProxyGenerator();
            $fileName  = $generator->getProxyFileName($className);

            if ($this->autoGenerate) {
                $generator->generateProxyClass($classMetadata);
            }

            require $fileName;
        }

        /* @var $reflectionId \ReflectionProperty */
        $reflectionId = $classMetadata->reflFields[$classMetadata->identifier];

        $this->definitions[$className] = array(
            'fqcn'         => $fqcn,
            'initializer'  => $this->createInitializer($classMetadata, $this->dm),
            'cloner'       => $this->createCloner($classMetadata, $this->dm, $reflectionId, $fqcn),
            'reflectionId' => $reflectionId,
        );
    }

    /**
     * Generates a closure capable of initializing a proxy
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Doctrine\ODM\CouchDB\DocumentManager              $documentManager
     *
     * @return callable
     */
    private function createInitializer(
        ClassMetadata $classMetadata,
        DocumentManager $documentManager
    ) {
        if ($classMetadata->getReflectionClass()->hasMethod('__wakeup')) {
            return function (Proxy $proxy) use ($documentManager) {
                $proxy->__setInitializer(null);
                $proxy->__setCloner(null);

                if ($proxy->__isInitialized()) {
                    return;
                }

                $properties = $proxy->__getLazyProperties();

                foreach ($properties as $propertyName => $property) {
                    if (!isset($proxy->$propertyName)) {
                        $proxy->$propertyName = $properties[$propertyName];
                    }
                }

                $proxy->__setInitialized(true);
                $proxy->__wakeup();
                $documentManager->refresh($proxy);
            };
        }

        return function (Proxy $proxy) use ($documentManager) {
            $proxy->__setInitializer(null);
            $proxy->__setCloner(null);

            if ($proxy->__isInitialized()) {
                return;
            }

            $properties = $proxy->__getLazyProperties();

            foreach ($properties as $propertyName => $property) {
                if (!isset($proxy->$propertyName)) {
                    $proxy->$propertyName = $properties[$propertyName];
                }
            }

            $proxy->__setInitialized(true);
            $documentManager->refresh($proxy);
        };
    }

    /**
     * Generates a closure capable of finalizing a cloned proxy
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Doctrine\ODM\CouchDB\DocumentManager              $documentManager
     * @param ReflectionProperty                                 $reflectionId
     * @param                                                    $fqcn
     *
     * @return callable
     * @throws \Doctrine\Common\Proxy\Exception\UnexpectedValueException
     */
    private function createCloner(
        ClassMetadata $classMetadata,
        DocumentManager $documentManager,
        ReflectionProperty $reflectionId,
        $fqcn
    ) {
        return function (Proxy $proxy) use ($documentManager, $classMetadata, $reflectionId, $fqcn) {
            if ($proxy->__isInitialized()) {
                return;
            }

            $proxy->__setInitialized(true);
            $proxy->__setInitializer(null);

            $id       = $reflectionId->getValue($proxy);
            $original = $documentManager->find($fqcn, $id);

            if (null === $original) {
                throw new UnexpectedValueException(sprintf('Proxy could with ID "%s"not be loaded', $id));
            }

            foreach ($classMetadata->getReflectionClass()->getProperties() as $reflectionProperty) {
                $propertyName = $reflectionProperty->getName();

                if ($classMetadata->hasField($propertyName) || $classMetadata->hasAssociation($propertyName)) {
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($proxy, $reflectionProperty->getValue($original));
                }
            }
        };
    }
}

