<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid;

use ADT\FancyAdmin\Model\Entities\Enums\GridFilterType;
use ADT\FancyAdmin\Model\Entities\GridFilter;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\UI\Components\Grids\GridPreset;
use ADT\FancyAdmin\UI\Components\Grids\GridPresetException;
use ADT\FancyAdmin\UI\Components\Grids\GridPresetStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Sestavy gridů v tabulce uložených filtrů projektu (fancyadmin: datagrid: presetEntity), typ Preset. Uživatel
 * vidí svoje a sdílené sestavy; měnit a mazat smí svoje, uživatel s právem na plná data i cizí sdílené.
 * V projektu s účty patří sestava k vybranému účtu, v backoffice bez účtu je společná.
 */
class DoctrineGridPresetStorage implements GridPresetStorage
{
	public const int NAME_MAX_LENGTH = 100;

	private const string ERROR = 'fcadmin.datagrid.columnFilters.presets.';

	/** @param class-string<GridFilter> $entityClass */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly SecurityUser $securityUser,
		private readonly string $entityClass,
	) {
		if (!is_a($entityClass, GridFilter::class, true)) {
			throw new InvalidArgumentException(sprintf('Entita uloženého filtru %s musí implementovat %s.', $entityClass, GridFilter::class));
		}
	}

	public function findAll(string $grid): array
	{
		$identity = $this->getIdentity();
		if (!$identity) {
			return [];
		}

		/** @var list<GridFilter> $filters */
		$filters = $this->createVisibleQuery($grid, $identity)->orderBy('f.name')->getQuery()->getResult();
		$presets = array_map(fn(GridFilter $filter): GridPreset => $this->toPreset($filter, $identity), $filters);
		usort($presets, static fn(GridPreset $a, GridPreset $b): int => $b->own <=> $a->own);

		return $presets;
	}

	public function find(string $grid, int $id): ?GridPreset
	{
		$identity = $this->getIdentity();
		$filter = $identity ? $this->findVisible($grid, $id, $identity) : null;

		return $filter ? $this->toPreset($filter, $identity) : null;
	}

	public function create(string $grid, string $name, bool $shared, array $filters, array $columns): GridPreset
	{
		$identity = $this->getIdentity() ?? throw new GridPresetException('', GridPresetException::FORBIDDEN);
		$name = $this->validateName($grid, $name, $identity);

		$filter = new $this->entityClass();
		$filter->setGrid($grid)
			->setName($name)
			->setType(GridFilterType::Preset)
			->setShared($shared)
			->setValue($filters)
			->setColumns($columns)
			->setCreatedBy($identity);
		$this->em->persist($filter);
		$this->em->flush();

		return $this->toPreset($filter, $identity);
	}

	public function update(string $grid, int $id, string $name, bool $shared, ?array $filters = null, ?array $columns = null): GridPreset
	{
		[$filter, $identity] = $this->findEditable($grid, $id);
		$name = $this->validateName($grid, $name, $filter->getCreatedBy(), $filter);

		$filter->setName($name)->setShared($shared);
		if ($filters !== null) {
			$filter->setValue($filters);
		}
		if ($columns !== null) {
			$filter->setColumns($columns);
		}
		$this->em->flush();

		return $this->toPreset($filter, $identity);
	}

	public function delete(string $grid, int $id): void
	{
		[$filter] = $this->findEditable($grid, $id);

		$this->em->remove($filter);
		$this->em->flush();
	}

	/** @return array{GridFilter, Identity} */
	private function findEditable(string $grid, int $id): array
	{
		$identity = $this->getIdentity() ?? throw new GridPresetException('', GridPresetException::FORBIDDEN);
		$filter = $this->findVisible($grid, $id, $identity) ?? throw new GridPresetException('', GridPresetException::NOT_FOUND);
		if (!$this->isEditable($filter, $identity)) {
			throw new GridPresetException('', GridPresetException::FORBIDDEN);
		}

		return [$filter, $identity];
	}

	private function findVisible(string $grid, int $id, Identity $identity): ?GridFilter
	{
		return $this->createVisibleQuery($grid, $identity)
			->andWhere('f.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getOneOrNullResult();
	}

	private function createVisibleQuery(string $grid, Identity $identity): QueryBuilder
	{
		return $this->createQuery($grid)
			->andWhere('f.createdBy = :identity OR f.shared = true')
			->setParameter('identity', $identity);
	}

	private function createQuery(string $grid): QueryBuilder
	{
		$qb = $this->em->createQueryBuilder()
			->select('f')
			->from($this->entityClass, 'f')
			->where('f.grid = :grid')
			->andWhere('f.type = :type')
			->setParameter('grid', $grid)
			->setParameter('type', GridFilterType::Preset->value);

		$account = $this->securityUser->getIdentity()?->getSelectedAccount();

		return $account
			? $qb->andWhere('f.account = :account')->setParameter('account', $account)
			: $qb->andWhere('f.account IS NULL');
	}

	private function isEditable(GridFilter $filter, Identity $identity): bool
	{
		return $filter->getCreatedBy()?->getId() === $identity->getId() || $this->securityUser->isAllowedFullDataAclResource();
	}

	/** Název je povinný a autor nesmí mít v gridu dvě sestavy stejného jména. */
	private function validateName(string $grid, string $name, ?Identity $owner, ?GridFilter $current = null): string
	{
		$name = mb_substr(trim($name), 0, self::NAME_MAX_LENGTH);
		if ($name === '') {
			throw new GridPresetException(self::ERROR . 'nameRequired', GridPresetException::INVALID);
		}

		$qb = $this->createQuery($grid)
			->andWhere('f.name = :name')
			->setParameter('name', $name);
		$owner
			? $qb->andWhere('f.createdBy = :owner')->setParameter('owner', $owner)
			: $qb->andWhere('f.createdBy IS NULL');
		if ($current) {
			$qb->andWhere('f.id != :current')->setParameter('current', $current->getId());
		}

		if ($qb->setMaxResults(1)->getQuery()->getOneOrNullResult()) {
			throw new GridPresetException(self::ERROR . 'nameTaken', GridPresetException::INVALID);
		}

		return $name;
	}

	private function toPreset(GridFilter $filter, Identity $identity): GridPreset
	{
		$own = $filter->getCreatedBy()?->getId() === $identity->getId();

		return new GridPreset(
			$filter->getId(),
			$filter->getName(),
			$filter->isShared(),
			$own,
			$this->isEditable($filter, $identity),
			$own ? null : $filter->getCreatedBy()?->getFullName(),
			$filter->getValue(),
			$filter->getColumns() ?? [],
		);
	}

	/** Identita z přihlášení může být po EntityManager::clear() odpojená, nový záznam ji ale potřebuje spravovanou. */
	private function getIdentity(): ?Identity
	{
		$identity = $this->securityUser->getIdentity();
		if (!$identity instanceof Identity) {
			return null;
		}

		return $this->em->contains($identity) ? $identity : $this->em->getReference($identity::class, $identity->getId());
	}
}
