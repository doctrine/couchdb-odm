<?php

declare( strict_types=1 );

namespace Doctrine\ODM\CouchDB\Proxy;

use Closure;
use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionProperty;

/**
 * Static factory for proxy objects.
 *
 * @internal this class is to be used by ORM internals only
 */
final class StaticProxyFactory implements ProxyFactory {
	private const SKIPPED_PROPERTIES = 'skippedProperties';

	/** @var DocumentManager */
	private $dm;

	/** @var LazyLoadingGhostFactory */
	private $proxyFactory;

	/** @var Closure[] indexed by metadata class name */
	private $cachedInitializers = [];

	/** @var string[][][] indexed by metadata class name */
	private $cachedSkippedProperties = [];

	public function __construct(
		DocumentManager $dm,
		LazyLoadingGhostFactory $proxyFactory
	) {
		$this->dm           = $dm;
		$this->proxyFactory = $proxyFactory;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ClassMetadata[] $classes
	 */
	public function generateProxyClasses( array $classes ): int {
		$concreteClasses = array_filter( $classes,
			static function ( ClassMetadata $metadata ): bool {
				return ! ( $metadata->isMappedSuperclass || $metadata->getReflectionClass()->isAbstract() );
			} );
		foreach ( $concreteClasses as $metadata ) {
			$this
				->proxyFactory
				->createProxy(
					$metadata->getName(),
					static function () {
						// empty closure, serves its purpose, for now
					},
					$this->skippedFieldsFqns( $metadata )
				);
		}

		return count( $concreteClasses );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ClassMetadata $metadata
	 * @param string $identifier
	 *
	 * @return GhostObjectInterface
	 */
	public function getProxy( ClassMetadata $metadata, string $identifier ): GhostObjectInterface {
		$className     = $metadata->getName();
		$proxyInstance = $this
			->proxyFactory
			->createProxy(
				$metadata->getName(),
				$this->cachedInitializers[ $className ]
				?? $this->cachedInitializers[ $className ] = $this->makeInitializer( $metadata ),
				$this->cachedSkippedProperties[ $className ]
				?? $this->cachedSkippedProperties[ $className ] = [
					self::SKIPPED_PROPERTIES => $this->skippedFieldsFqns( $metadata ),
				]
			);
		$metadata->setIdentifierValue($proxyInstance, $identifier);

		if ( $this->dm->getClassMetadataFactory() && ! $this->dm->getClassMetadataFactory()->hasMetadataFor(get_class($proxyInstance))  ) {
			$this->dm->getClassMetadataFactory()->setMetadataFor( get_class( $proxyInstance ), $metadata );
		}

		return $proxyInstance;
	}

	/**
	 * @param ClassMetadata $metadata
	 *
	 * @return Closure
	 */
	private function makeInitializer( ClassMetadata $metadata ): Closure {
		$dm = $this->dm;
		return static function (
			GhostObjectInterface $ghostObject,
			string $method,
			// we don't care
			array $parameters,
			// we don't care
			& $initializer,
			array $properties // we currently do not use this
		) use (
			$metadata,
			$dm
		) : bool {
			$initializer         = null;

			$dm->refresh($ghostObject);

			return true;
		};
	}

	/**
	 * @param ClassMetadata $metadata
	 *
	 * @return string[]
	 */
	private function skippedFieldsFqns( ClassMetadata $metadata ): array {
		return array_merge(
			$this->identifierFieldFqns( $metadata ),
			$this->transientFieldsFqns( $metadata )
		);
	}

	/**
	 * @param ClassMetadata $metadata
	 *
	 * @return string[]
	 */
	private function transientFieldsFqns( ClassMetadata $metadata ): array {
		return [];

		$transientFieldsFqns = [];
		foreach ( $metadata->fieldMappings as $name => $property ) {
			// TODO : Transient fields don't have mapping
			/*
			if ( ! $property instanceof TransientMetadata ) {
				continue;
			}
			$transientFieldsFqns[] = $this->propertyFqcn( $metadata->getReflectionProperty( $name ) );
			*/
		}

		return $transientFieldsFqns;
	}

	/**
	 * @param ClassMetadata $metadata
	 *
	 * @return string[]
	 */
	private function identifierFieldFqns( ClassMetadata $metadata ): array {
		$idFieldFqcns = [];
		foreach ( $metadata->getIdentifierFieldNames() as $idField ) {
			if ( !$idField ) {
				continue;
			}
			$idFieldFqcns[] = $this->propertyFqcn( $metadata->getReflectionProperty( $idField ) );
		}

		return $idFieldFqcns;
	}

	private function propertyFqcn( ReflectionProperty $property ): string {
		if ( $property->isPrivate() ) {
			return "\0" . $property->getDeclaringClass()->getName() . "\0" . $property->getName();
		}
		if ( $property->isProtected() ) {
			return "\0*\0" . $property->getName();
		}

		return $property->getName();
	}
}