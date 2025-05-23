<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedAt;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Nette\Utils\Random;

trait OnetimeTokenTrait
{
	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[Column(nullable: false)]
	protected string $token;

	#[Column(nullable: false)]
	protected string $type;

	#[Column(nullable: false)]
	protected int $objectId;

	#[Column(nullable: true)]
	protected ?DateTimeImmutable $usedAt = null;

	#[Column(nullable: false)]
	protected DateTimeImmutable $validUntil;

	public function getToken(): string
	{
		return $this->token;
	}

	public function setToken(string $token): static
	{
		$this->token = $token;
		return $this;
	}

	public function getUsedAt(): ?DateTimeImmutable
	{
		return $this->usedAt;
	}

	public function setUsedAt(?DateTimeImmutable $usedAt): static
	{
		$this->usedAt = $usedAt;
		return $this;
	}

	public static function generateRandomToken(): string
	{
		return Random::generate(32, 'a-zA-Z0-9');
	}

	public function setValidUntil(DateTimeImmutable $validUntil): static
	{
		$this->validUntil = $validUntil;
		return $this;
	}

	public function getValidUntil(): DateTimeImmutable
	{
		return $this->validUntil;
	}

	public function isValid(): bool
	{
		// Omezujeme jenom validitu u password Recovery
		if ($this->validUntil < (new DateTimeImmutable('now'))) {
			return false;
		}

		return true;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function setType(string $type): static
	{
		$this->type = $type;
		return $this;
	}

	public function getObjectId(): int
	{
		return $this->objectId;
	}

	public function setObjectId(?int $objectId): static
	{
		$this->objectId = $objectId;
		return $this;
	}
}
