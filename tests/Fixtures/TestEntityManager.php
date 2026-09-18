<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineComponents\EntityManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Exception;
use ReflectionClass;

/**
 * EntityManager, ktery misto databaze vraci pole entit drzene v pameti.
 * Staci na kod, ktery si jen necha najit entitni tridu a vytahnout zaznamy.
 */
final class TestEntityManager extends EntityManager
{
	/** @var array<class-string, TestRepository> */
	private array $repositories = [];

	/** @var list<class-string> */
	private array $entityClasses;

	private ?TestConnection $testConnection = null;

	/** @var array<class-string, ClassMetadata> */
	private array $metadata = [];

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct(array $entityClasses = [])
	{
		$this->entityClasses = $entityClasses;
	}

	public function getConnection(): Connection
	{
		return $this->testConnection ??= new TestConnection();
	}

	public function getClassMetadata(string $className): ClassMetadata
	{
		if (!isset($this->metadata[$className])) {
			$metadata = new ClassMetadata($className);
			$metadata->initializeReflection(new RuntimeReflectionService());
			$this->metadata[$className] = $metadata;
		}

		return $this->metadata[$className];
	}

	public function getMetadataFactory(): ClassMetadataFactory
	{
		return new TestClassMetadataFactory(array_map($this->getClassMetadata(...), $this->entityClasses));
	}

	public function getRepository(string $className): EntityRepository
	{
		return $this->repositories[$className] ??= new TestRepository();
	}

	public function findEntityClassByInterface(string $interfaceName): string
	{
		foreach ($this->entityClasses as $className) {
			if (new ReflectionClass($className)->implementsInterface($interfaceName)) {
				return $className;
			}
		}

		throw new Exception('There is no entity with interface "' . $interfaceName . '".');
	}
}

/**
 * Repozitar, ktery si pamatuje, s jakymi kriterii se ho kdo ptal - testy tak muzou
 * overit i to, ze se filtruje na spravny sloupec.
 */
final class TestRepository extends EntityRepository
{
	/** @var list<object> */
	public array $entities = [];

	/** @var list<array> */
	public array $findByCalls = [];

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct()
	{
	}

	public function findAll(): array
	{
		return $this->entities;
	}

	public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
	{
		$this->findByCalls[] = $criteria;

		return array_values(array_filter(
			$this->entities,
			fn(object $entity) => self::matches($entity, $criteria),
		));
	}

	public function findOneBy(array $criteria, ?array $orderBy = null): ?object
	{
		return $this->findBy($criteria)[0] ?? null;
	}

	private static function matches(object $entity, array $criteria): bool
	{
		foreach ($criteria as $property => $value) {
			$getter = 'get' . ucfirst($property);
			if ($entity->$getter() !== $value) {
				return false;
			}
		}

		return true;
	}
}

/** Tovarna na metadata nad pevne danym seznamem entit. */
final class TestClassMetadataFactory extends ClassMetadataFactory
{
	/** @var list<ClassMetadata> */
	private array $testMetadata;

	/** @param list<ClassMetadata> $metadata */
	public function __construct(array $metadata = [])
	{
		$this->testMetadata = $metadata;
	}

	public function getAllMetadata(): array
	{
		return $this->testMetadata;
	}

	public function getMetadataFor(string $className): ClassMetadata
	{
		foreach ($this->testMetadata as $metadata) {
			if ($metadata->getName() === $className) {
				return $metadata;
			}
		}

		throw new Exception('Neznama entita ' . $className . '.');
	}

	public function hasMetadataFor(string $className): bool
	{
		foreach ($this->testMetadata as $metadata) {
			if ($metadata->getName() === $className) {
				return true;
			}
		}

		return false;
	}

	public function isTransient(string $className): bool
	{
		return false;
	}
}
