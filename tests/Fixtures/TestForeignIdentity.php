<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;

/** Identita z jineho balicku - fancyadmin ji musi umet ignorovat. */
final class TestForeignIdentity implements DoctrineAuthenticatorIdentity
{
	public function getId(): int
	{
		return 1;
	}

	public function getRoles(): array
	{
		return [];
	}

	public function getAuthObjectId(): string
	{
		return '1';
	}

	public function getAuthToken(): string
	{
		return 'token';
	}

	public function setAuthToken(string $token): void
	{
	}

	public function getAuthMetadata(): array
	{
		return [];
	}

	public function setAuthMetadata(array $metadata): void
	{
	}

	public function getContext(): ?string
	{
		return null;
	}

	public function setContext(?string $context): static
	{
		return $this;
	}
}
