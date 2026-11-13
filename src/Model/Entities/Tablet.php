<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

/**
 * Tablet, na kterém zákazníci (pacienti, klienti) vyplňují a podepisují formuláře.
 * Do administrace se nepřihlašuje: jednou se spáruje QR kódem a dostane klíč zařízení
 * (cookie), formuláře mu pak chodí jako žádosti o podpis (TabletSignRequest), na které ho
 * upozorní Web Push. Sloupce a metody dává TabletTrait, logiku TabletService, viz docs/tablets.md.
 */
interface Tablet extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;

	public function getPairingTokenHash(): ?string;
	public function getPairingExpiresAt(): ?DateTimeImmutable;
	public function setPairingToken(?string $hash, ?DateTimeImmutable $expiresAt): static;

	public function getDeviceSecretHash(): ?string;
	public function pair(string $deviceSecretHash, DateTimeImmutable $at): static;
	public function disconnect(): static;
	public function isPaired(): bool;

	public function getPushEndpoint(): ?string;
	public function setPushEndpoint(?string $pushEndpoint): static;
	public function canReceiveForms(): bool;

	public function getPairedAt(): ?DateTimeImmutable;
	public function getLastSeenAt(): ?DateTimeImmutable;
	public function setLastSeenAt(DateTimeImmutable $lastSeenAt): static;

	public function getCreatedAt(): DateTimeImmutable;
}
