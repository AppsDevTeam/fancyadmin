<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;

/**
 * `created_at` v UTC - pro logovací entity.
 *
 * Zbytek aplikace ukládá časy v zóně projektu (`date.timezone`), logy ale ne: odvážejí se
 * do společného úložiště vedle záznamů, které UTC píšou vždycky (request_log, api_log,
 * auth_log, audit_log), a dokument pro ČSOB u logů UTC slibuje. Kdyby si každá tabulka
 * nesla jinou zónu, nedaly by se mezi sebou porovnat a nikde by to nebylo vidět.
 *
 * Administrace je i tak ukazuje v čase projektu - převádí se to až při čtení, viz
 * ADT\FancyAdmin\Model\Doctrine\SessionTimeZoneMiddleware.
 *
 * Proti traitě CreatedAt tu není Gedmo Timestampable: ten bere čas v zóně aplikace.
 * Entita musí mít #[ORM\HasLifecycleCallbacks], jinak se razítko nezavolá.
 */
trait CreatedAtUtc
{
	#[ORM\Column]
	protected DateTimeImmutable $createdAt;

	#[ORM\PrePersist]
	public function stampCreatedAtUtc(): void
	{
		// ?? = kdyz si cas nastavil volajici (import, migrace dat), nechame mu ho
		$this->createdAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;
		return $this;
	}
}
