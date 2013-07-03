<?php

namespace Doctrine\Tests\ODM\CouchDB\Proxy;

use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
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

    public function testReferenceProxyDelegatesLoadingToThePersister()
    {
        $proxyClass = 'Proxies\__CG__\Doctrine\Tests\Models\ECommerce\ECommerceFeature';
        $modelClass = 'Doctrine\Tests\Models\ECommerce\ECommerceFeature';

        $query = array('documentName' => '\\'.$modelClass, 'id' => 'SomeUUID');

        $uowMock = $this->getMock('Doctrine\ODM\CouchDB\UnitOfWork', array('refresh'), array(), '', false);
        $uowMock->expects($this->atLeastOnce())
                      ->method('refresh')
                      ->with($this->isInstanceOf($proxyClass));

        $dmMock = new DocumentManagerMock();
        $dmMock->setUnitOfWorkMock($uowMock);

        $this->proxyFactory = new ProxyFactory($dmMock, __DIR__ . '/generated', 'Proxies', true);

        $proxy = $this->proxyFactory->getProxy($modelClass, $query['id'], $query['documentName']);

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Proxy\Proxy', $proxy);

        $proxy->getDescription();
    }
}

class DocumentManagerMock extends \Doctrine\ODM\CouchDB\DocumentManager
{
    private $uowMock;

    public function __construct()
    {
        
    }

    public function getClassMetadata($class)
    {
        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->wakeupReflection(new RuntimeReflectionService());

        return $metadata;
    }

    public function getMetadataFactory()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create(array('dbname' => 'test'));
        return new \Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory($dm);
    }

    public function setUnitOfWorkMock($mock)
    {
        $this->uowMock = $mock;
    }

    public function getUnitOfWork()
    {
        return $this->uowMock;
    }

}
