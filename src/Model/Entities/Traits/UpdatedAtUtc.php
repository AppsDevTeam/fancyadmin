<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;

/**
 * `updated_at` v UTC - protějšek CreatedAtUtc pro logovací entity.
 *
 * Řádek loguje jeden děj, takže obě jeho razítka musí být ve stejné zóně; jinak by
 * v odděleném úložišti ukazovala každá kolonka jinam.
 */
trait UpdatedAtUtc
{
	#[ORM\Column]
	protected DateTimeImmutable $updatedAt;

	#[ORM\PrePersist]
	#[ORM\PreUpdate]
	public function stampUpdatedAtUtc(): void
	{
		$this->updatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $updatedAt): static
	{
		$this->updatedAt = $updatedAt;
		return $this;
	}
}
