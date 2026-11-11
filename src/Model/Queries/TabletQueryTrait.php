<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use Doctrine\ORM\QueryBuilder;

trait TabletQueryTrait
{
	public function byPairingTokenHash(string $hash): static
	{
		return $this->by('pairingTokenHash', $hash);
	}

	public function byDeviceSecretHash(string $hash): static
	{
		return $this->by('deviceSecretHash', $hash);
	}

	/**
	 * Spárované tablety (Tablet::isPaired). S dotazováním (fancyadmin: tablet: pollInterval)
	 * jde formulář poslat na každý z nich - bez notifikací ho vyzvedne otevřená čekací stránka.
	 */
	public function byPaired(): static
	{
		$this->filter[] = function (QueryBuilder $qb): void {
			$qb->andWhere('e.deviceSecretHash IS NOT NULL');
		};
		return $this;
	}

	/** Spárované tablety s povolenými notifikacemi (Tablet::canReceiveForms) */
	public function byCanReceiveForms(): static
	{
		$this->filter[] = function (QueryBuilder $qb): void {
			$qb->andWhere('e.deviceSecretHash IS NOT NULL')
				->andWhere('e.pushEndpoint IS NOT NULL');
		};
		return $this;
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy(['name' => 'ASC']);
	}
}
