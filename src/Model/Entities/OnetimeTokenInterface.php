<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Nette\Utils\Random;

interface OnetimeTokenInterface
{
	public function getToken(): string;
	public function setToken(string $token): static;
	public function getObjectId(): int;
	public function setObjectId(int $obejctId): static;
	public function getUsedAt(): ?DateTimeImmutable;
	public function setUsedAt(?DateTimeImmutable $usedAt): static;
	public static function generateRandomToken(): string;
	public function setValidUntil(DateTimeImmutable $validUntil): static;
	public function getValidUntil(): DateTimeImmutable;
	public function isValid(): bool;
	public function getType(): string;
	public function setType(string $type): static;
}
