<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

interface RequestLog extends Entity
{
	public function getCreatedAt(): DateTimeImmutable;
	public function setCreatedAt(DateTimeImmutable $createdAt): static;
	public function getIdentityId(): ?int;
	public function setIdentityId(?int $identityId): static;
	public function getApiKeyId(): ?int;
	public function setApiKeyId(?int $apiKeyId): static;
	public function getUrl(): string;
	public function setUrl(string $url): static;
	public function getMethod(): string;
	public function setMethod(string $method): static;
	public function getCode(): int;
	public function setCode(int $code): static;
	public function getIp(): string;
	public function setIp(string $ip): static;
	public function getResponseTime(): ?float;
	public function setResponseTime(?float $responseTime): static;
}
