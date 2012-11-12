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
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Proxy\ProxyGenerator;

/**
 * This factory is used to create proxy objects for entities at runtime.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
 * @author Nils Adermann <naderman@naderman.de>
 *
 * This whole thing is copy & pasted from ORM - should really be slightly
 * refactored to generate
 */
class ProxyFactory
{
    /** The DocumentManager this factory is bound to. */
    private $dm;
    /**
     * @var ProxyGenerator
     */
    private $proxyGenerator;

    /** Whether to automatically (re)generate proxy classes. */
    private $autoGenerate;
    /** The namespace that contains all proxy classes. */
    private $proxyNamespace;
    /** The directory that contains all proxy classes. */
    private $proxyDir;

    /**
     * Initializes a new instance of the <tt>ProxyFactory</tt> class that is
     * connected to the given <tt>DocumentManager</tt>.
     *
     * @param DocumentManager $dm The DocumentManager the new factory works for.
     * @param string $proxyDir The directory to use for the proxy classes. It must exist.
     * @param string $proxyNs The namespace to use for the proxy classes.
     * @param boolean $autoGenerate Whether to automatically generate proxy classes.
     */
    public function __construct(DocumentManager $dm, $proxyDir, $proxyNs, $autoGenerate = false)
    {
        $this->dm = $dm;
        $this->proxyDir = $proxyDir;
        $this->autoGenerate = $autoGenerate;
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
        $fqn      = ClassUtils::generateProxyClassName($className, $this->proxyNamespace);
        $dm       = $this->dm;
        $metadata = $dm->getClassMetadata($className);

        if ( ! class_exists($fqn, false)) {
            $generator = $this->getProxyGenerator();
            $fileName = $generator->getProxyFileName($className);

            if ($this->autoGenerate) {
                $generator->generateProxyClass($metadata);
            }

            require $fileName;
        }

        $initializer = function (Proxy $proxy) use ($dm) {
            $proxy->__setInitializer(function () {});
            $proxy->__setCloner(function () {});

            if ($proxy->__isInitialized()) {
                return;
            }

            $properties = $proxy->__getLazyLoadedPublicProperties();

            foreach ($properties as $propertyName => $property) {
                if (!isset($proxy->$propertyName)) {
                    $proxy->$propertyName = $properties[$propertyName];
                }
            }

            $proxy->__setInitialized(true);

            if (method_exists($proxy, '__wakeup')) {
                $proxy->__wakeup();
            }

            $dm->refresh($proxy);
        };

        $cloner = function (Proxy $cloned) use ($dm, $identifier) {
            if ($cloned->__isInitialized()) {
                return;
            }

            $cloned->__setInitialized(true);
            $cloned->__setInitializer(function () {});
            $className = get_class($cloned);
            $class = $dm->getClassMetadata($className);
            $original = $dm->find($className, $identifier);

            if (null === $original) {
                throw new UnexpectedValueException(sprintf('Proxy could with ID "%s"not be loaded', $identifier));
            }

            foreach ($class->getReflectionClass()->getProperties() as $reflectionProperty) {
                $propertyName = $reflectionProperty->getName();

                if ($class->hasField($propertyName) || $class->hasAssociation($propertyName)) {
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($cloned, $reflectionProperty->getValue($original));
                }
            }
        };

        $proxy = new $fqn($initializer, $cloner);

        foreach ($metadata->getIdentifierFieldNames() as $idField) {
            $metadata->setFieldValue($proxy, $idField, $identifier);

            break; // CouchDB supports only a single identifier field
        }

        return $proxy;
    }

    /**
     * Generates proxy classes for all given classes.
     *
     * @param ClassMetadata[] $classes The classes for which to generate proxies.
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

            $generator = $this->getProxyGenerator();

            $proxyFileName = $generator->getProxyFileName($class->getName(), $toDir);
            $generator->generateProxyClass($class, $proxyFileName);
            $generated += 1;
        }

        return $generated;
    }

    /**
     * @param ProxyGenerator $proxyGenerator
     */
    public function setProxyGenerator(ProxyGenerator $proxyGenerator)
    {
        $this->proxyGenerator = $proxyGenerator;
    }

    /**
     * @return ProxyGenerator
     */
    public function getProxyGenerator()
    {
        if (null === $this->proxyGenerator) {
            $this->proxyGenerator = new ProxyGenerator($this->proxyDir, $this->proxyNamespace);
            $this->proxyGenerator->setPlaceholder('<baseProxyInterface>', 'Doctrine\ODM\CouchDB\Proxy\Proxy');
        }

        return $this->proxyGenerator;
    }
}

