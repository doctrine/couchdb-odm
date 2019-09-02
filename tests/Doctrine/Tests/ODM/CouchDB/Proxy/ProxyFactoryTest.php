<?php

namespace Doctrine\Tests\ODM\CouchDB\Proxy;

use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\ODM\CouchDB\Configuration;
use Doctrine\ODM\CouchDB\Proxy\StaticProxyFactory;

use ProxyManager\Proxy\GhostObjectInterface;

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
        $proxyClass = 'ProxyManagerGeneratedProxy\__PM__\Doctrine\Tests\Models\ECommerce\ECommerceFeature\Generated37e3d1ba7ea56b5611071c13770ad452';
        $modelClass = 'Doctrine\Tests\Models\ECommerce\ECommerceFeature';

        $query = array('documentName' => '\\'.$modelClass, 'id' => 'SomeUUID');

        $uowMock = $this->getMock('Doctrine\ODM\CouchDB\UnitOfWork', array('refresh'), array(), '', false);
        $uowMock->expects($this->atLeastOnce())
                      ->method('refresh')
                      ->with($this->isInstanceOf($proxyClass));

        $dmMock = new DocumentManagerMock();
        $dmMock->setUnitOfWorkMock($uowMock);

        $configuration = new Configuration();
	    $configuration->setProxyDir(__DIR__ . '/generated');

        $this->proxyFactory = new StaticProxyFactory($dmMock, $configuration->buildGhostObjectFactory());

        $proxy = $this->proxyFactory->getProxy($dmMock->getClassMetadata($modelClass), $query['id']);

        $this->assertInstanceOf( GhostObjectInterface::class, $proxy);

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
	    $metadata->mapField(array('fieldName' => 'id', 'id' => true));
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
