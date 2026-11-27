<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid;

use ADT\FancyAdmin\Model\Entities\GridColumnSetting;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\UI\Components\Grids\GridColumnSettingsStorage;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Nastavení sloupců u přihlášené identity v entitě projektu (fancyadmin: datagrid: columnSettingEntity).
 * Bez přihlášení se nic neukládá a grid má výchozí sloupce.
 */
class DoctrineGridColumnSettingsStorage implements GridColumnSettingsStorage
{
	/** @param class-string<GridColumnSetting> $entityClass */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly SecurityUser $securityUser,
		private readonly string $entityClass,
	) {
		if (!is_a($entityClass, GridColumnSetting::class, true)) {
			throw new InvalidArgumentException(sprintf('Entita nastavení sloupců %s musí implementovat %s.', $entityClass, GridColumnSetting::class));
		}
	}

	public function load(string $grid): array
	{
		$identity = $this->getIdentity();

		return $identity ? ($this->find($grid, $identity)?->getSettings() ?? []) : [];
	}

	public function save(string $grid, array $settings): void
	{
		$identity = $this->getIdentity();
		if (!$identity) {
			return;
		}

		$setting = $this->find($grid, $identity);
		if (!$setting) {
			$setting = new $this->entityClass();
			$setting->setIdentity($identity)->setGrid($grid);
			$this->em->persist($setting);
		}

		$setting->setSettings($settings);
		$this->em->flush();
	}

	private function find(string $grid, Identity $identity): ?GridColumnSetting
	{
		return $this->em->getRepository($this->entityClass)->findOneBy(['identity' => $identity, 'grid' => $grid]);
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
