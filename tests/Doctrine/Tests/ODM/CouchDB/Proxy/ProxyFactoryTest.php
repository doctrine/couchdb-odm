<?php

namespace Doctrine\Tests\ODM\CouchDB\Proxy;

use Doctrine\ODM\CouchDB\Proxy\ProxyFactory;
use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\UnitOfWork;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
use Doctrine\Tests\Models\ECommerce\ECommerceCart;
use Doctrine\Tests\Models\ECommerce\ECommerceCustomer;
use Doctrine\Tests\Models\ECommerce\ECommerceFeature;
use Doctrine\Tests\Models\ECommerce\ECommerceProduct;
use Doctrine\Tests\Models\ECommerce\ECommerceShipping;

/**
 * Test the proxy factory.
 * @author Nils Adermann <naderman@naderman.de>
 */
class ProxyFactoryTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBTestCase
{
    private $uowMock;
    private $dmMock;
    private $persisterMock;

    /**
     * @var \Doctrine\ODM\CouchDB\Proxy\ProxyFactory
     */
    private $proxyFactory;

    protected function setUp()
    {
        parent::setUp();
    }

    protected function tearDown()
    {
        foreach (new \DirectoryIterator(__DIR__ . '/generated') as $file) {
            if (strstr($file->getFilename(), '.php')) {
                unlink($file->getPathname());
            }
        }
    }

    public function testProxyGeneration()
    {
        /** @todo id string only */
        $identifier = array('id' => 'SomeUUID');

        $proxyClass = 'Proxies\DoctrineTestsModelsECommerceECommerceFeatureProxy';
        $modelClass = 'Doctrine\Tests\Models\ECommerce\ECommerceFeature';

        $persisterMock = $this->getMock('Doctrine\ODM\CouchDB\Persisters\BasicDocumentPersister', array('load'), array(), '', false);
        $persisterMock->expects($this->atLeastOnce())
                      ->method('load')
                      ->with($this->equalTo($identifier), $this->isInstanceOf($proxyClass))
                      ->will($this->returnValue(new \stdClass())); // fake return of entity instance

        $uowMock = new UnitOfWorkMock($persisterMock);
        $dmMock = new DocumentManagerMock($uowMock);

        $this->proxyFactory = new ProxyFactory($dmMock, __DIR__ . '/generated', 'Proxies', true);

        $proxy = $this->proxyFactory->getProxy($modelClass, $identifier);

        $proxy->getDescription();
    }

    public function createDocumentManagerProvidingMetadataMock()
    {

    }
}

class UnitOfWorkMock extends \Doctrine\ODM\CouchDB\UnitOfWork
{
    private $persisterMock;

    public function __construct($persisterMock)
    {
        $this->persisterMock = $persisterMock;
    }

    public function getDocumentPersister()
    {
        return $this->persisterMock;
    }
}

class DocumentManagerMock extends \Doctrine\ODM\CouchDB\DocumentManager
{
    private $uowMock;

    public function __construct($uowMock)
    {
        $this->uowMock = $uowMock;
    }

    public function getClassMetadata($class)
    {
        return new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata($class);
    }

    public function getMetadataFactory()
    {
        return new \Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
    }

    public function getUnitOfWork()
    {
        return $this->uowMock;
    }
}
