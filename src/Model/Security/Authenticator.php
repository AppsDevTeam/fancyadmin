<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\LoginThrottleStatus;
use Nette\Security\IIdentity;

interface Authenticator
{
	public function findIdentity(string $identifier, ?string $context = null, array $metadata = []): ?IIdentity;
	public function authenticate(string $username, ?string $password = null, ?string $context = null, array $metadata = []): IIdentity;

	/**
	 * Kolik pokusu o prihlaseni jmenu zbyva a do kdy je zablokovane - prihlasovaci formular
	 * z toho misto obecneho "neplatne udaje" rekne, co se deje. Implementuje to
	 * {@see \ADT\DoctrineAuthenticator\DoctrineAuthenticator}, ze ktereho autentizator
	 * projektu dedi.
	 */
	public function getLoginThrottleStatus(string $username): LoginThrottleStatus;
	public function clearIdentity(int|string|null $objectId = null, array $metadata = []): void;
	public function getActiveSessions(string $objectId): array;
	public function clearSession(int $sessionId): void;
	public function getCurrentSessionId(): ?int;
}