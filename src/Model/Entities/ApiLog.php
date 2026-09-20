<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

interface ApiLog extends Entity
{
	public function getCreatedAt(): DateTimeImmutable;
	public function setCreatedAt(DateTimeImmutable $createdAt): static;
	public function getAccountId(): ?int;
	public function setAccountId(?int $accountId): static;
	public function getEnvironment(): ?string;
	public function setEnvironment(?string $environment): static;
	public function getEndpointUrl(): ?string;
	public function setEndpointUrl(?string $endpointUrl): static;
	public function getRequest(): ?string;
	public function setRequest(?string $request): static;
	public function getResponse(): ?string;
	public function setResponse(?string $response): static;
	public function getHttpStatusCode(): ?int;
	public function setHttpStatusCode(?int $httpStatusCode): static;
	public function getErrorMessage(): ?string;
	public function setErrorMessage(?string $errorMessage): static;
	public function getDurationMs(): ?int;
	public function setDurationMs(?int $durationMs): static;
}
