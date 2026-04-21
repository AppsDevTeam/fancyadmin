<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface Sso extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;

	public function getRealm(): string;
	public function setRealm(string $realm): static;

	public function getBaseUrl(): string;
	public function setBaseUrl(string $baseUrl): static;

	public function getHostUrl(): string;
	public function setHostUrl(string $hostUrl): static;

	public function getClientId(): string;
	public function setClientId(string $clientId): static;

	public function getClientSecret(): string;
	public function setClientSecret(string $clientSecret): static;

	public function getFrontendClientId(): string;
	public function setFrontendClientId(string $frontendClientId): static;

	public function getDefaultRole(): ?AclRole;
	public function setDefaultRole(?AclRole $defaultRole): static;
}
