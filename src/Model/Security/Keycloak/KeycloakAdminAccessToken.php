<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

class KeycloakAdminAccessToken
{
	private string $token;

	private \DateTime $expiresAt;

	public function __construct(string $token, int $expiresIn)
	{
		$this->token = $token;

		// nastavíme kvůli případný prodlevě na menší expires, ať je tam malá rezerva a případně se přegeneruje
		$expiresIn -= 5;

		$this->expiresAt = new \DateTime("+$expiresIn seconds");
	}

	public function getToken(): string
	{
		return $this->token;
	}

	public function isValid(): bool
	{
		$now = new \DateTime();

		return $now < $this->expiresAt;
	}
}
