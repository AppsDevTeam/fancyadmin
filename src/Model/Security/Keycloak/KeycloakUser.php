<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

class KeycloakUser
{
	private string $id;
	private string $username;
	private ?string $firstName = null;
	private ?string $lastName = null;
	private ?string $email = null;

	public function __construct(string $id, string $username, ?string $firstName, ?string $lastName, ?string $email)
	{
		$this->id = $id;
		$this->username = $username;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
		$this->email = $email;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getUsername(): string
	{
		return $this->username;
	}

	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	public function getLastName(): ?string
	{
		return $this->lastName;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}
}
