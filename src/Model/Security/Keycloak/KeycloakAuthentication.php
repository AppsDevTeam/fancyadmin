<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

class KeycloakAuthentication
{
	private string $accessToken;

	private ?string $refreshToken;

	private int $expiresIn;

	private int $refreshExpiresIn;

	private array $userInfo;

	private ?string $idToken;

	public function __construct(
		string $accessToken,
		?string $refreshToken,
		int $expiresIn,
		int $refreshExpiresIn,
		array $userInfo,
		?string $idToken = null
	) {
		$this->accessToken = $accessToken;
		$this->refreshToken = $refreshToken;
		$this->expiresIn = $expiresIn;
		$this->refreshExpiresIn = $refreshExpiresIn;
		$this->userInfo = $userInfo;
		$this->idToken = $idToken;
	}

	public function getAccessToken(): string
	{
		return $this->accessToken;
	}

	public function getRefreshToken(): string
	{
		return $this->refreshToken;
	}

	public function getExpiresIn(): \DateTime
	{
		return new \DateTime("+$this->expiresIn seconds");
	}

	public function getRefreshExpiresIn(): \DateTime
	{
		return new \DateTime("+$this->refreshExpiresIn seconds");
	}

	public function getUserInfo(): array
	{
		return $this->userInfo;
	}

	public function getIdToken(): ?string
	{
		return $this->idToken;
	}

	public function toArray(): array
	{
		return [
			'access_token' => $this->accessToken,
			'refresh_token' => $this->refreshToken,
			'expires_in' => $this->getExpiresIn()->format('Y-m-d H:i:s'),
			'refresh_expires_in' => $this->getRefreshExpiresIn()->format('Y-m-d H:i:s'),
			'id_token' => $this->idToken
		];
	}
}
