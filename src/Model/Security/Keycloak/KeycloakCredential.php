<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

use DateTimeImmutable;

/**
 * Credential uživatele v Keycloaku (heslo, WebAuthn klíč, OTP, …) tak, jak ho vrací
 * Admin API `GET /users/{id}/credentials`.
 *
 * Aplikace z credentialu drží jen metadata potřebná pro zobrazení a pro odebrání klíče —
 * `credentialData` ani `secretData` se záměrně nečtou, na klíče samotné aplikace nesahá.
 */
class KeycloakCredential
{
	public function __construct(
		private string $id,
		private string $type,
		private ?string $userLabel = null,
		private ?DateTimeImmutable $createdAt = null,
	) {
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getUserLabel(): ?string
	{
		return $this->userLabel;
	}

	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->createdAt;
	}
}
