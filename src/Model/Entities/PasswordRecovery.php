<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\Timestampable;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Nette\Utils\Random;

#[Entity]
class PasswordRecovery extends BaseEntity
{
	use Identifier;
	use Timestampable;

	/*
	  * Konstanty pro nastaveni jak dlouho je validni request pro obnovu hesla a jak dlouho je validni request v pripade
	 * vytvareni novehe uzivatele
	 */
	const int PASSWORD_RECOVERY_VALID_FOR = 1; //hour
	const int PASSWORD_CREATION_VALID_FOR = 72; //hours (3 days)


	#[Column(nullable: false)]
	protected string $token;

	#[ManyToOne(targetEntity: User::class)]
	#[JoinColumn(nullable: true)]
	protected ?User $user = null;

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

	public function getUser(): ?User
	{
		return $this->user;
	}

	public function setUser(?User $user): static
	{
		$this->user = $user;
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

//		Omezujeme jenom validitu u password Recovery
		if ($this->validUntil < (new DateTimeImmutable('now'))) {
			return false;
		}

		return true;
	}
}
