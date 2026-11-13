<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
use DateTimeImmutable;

/**
 * Žádost o podpis (formulář, dokument) poslaná na tablet - jeden řádek za každé odeslání.
 * Podle nevyzvednuté žádosti tablet pozná, že má co otevřít, a zároveň je to evidence: kdo,
 * kdy a co na který tablet poslal, kdy si to tablet otevřel a kdy se podepsalo.
 *
 * Co se podepisuje, drží žádost obecně (druh, ID záznamu, data) - nezávisle na entitách
 * projektu. Sloupce a metody dává TabletSignRequestTrait, logiku TabletService, viz
 * docs/tablets.md.
 */
interface TabletSignRequest extends Entity
{
	/** Nová žádost - vytváří TabletService::send() */
	public static function create(Tablet $tablet, string $url, string $title, DateTimeImmutable $now, DateTimeImmutable $expiresAt): static;

	/** null = tablet byl mezitím smazán (název zůstává v getTabletName()) */
	public function getTablet(): ?Tablet;
	public function getTabletName(): string;

	/** Odkaz, který tablet otevře */
	public function getUrl(): string;
	public function getTitle(): string;

	/** Druh podepisovaného dokumentu (kód projektu, např. "order"), null = projekt ho neuvádí */
	public function getType(): ?string;

	/** ID záznamu projektu, kterého se podpis týká */
	public function getSubjectId(): ?string;

	/** @return array<mixed>|null další údaje projektu */
	public function getData(): ?array;

	/** @param array<mixed>|null $data jen hodnoty serializovatelné do JSON */
	public function setSubject(?string $type, ?string $subjectId, ?array $data = null): static;

	/** Kdo žádost poslal (ID a jméno uživatele projektu v okamžiku odeslání) */
	public function getRequesterId(): ?string;
	public function getRequesterName(): ?string;
	public function setRequester(?string $id, ?string $name): static;

	public function getCreatedAt(): DateTimeImmutable;

	/** Do kdy si žádost tablet může vyzvednout */
	public function getExpiresAt(): DateTimeImmutable;
	public function getOpenedAt(): ?DateTimeImmutable;
	public function getSignedAt(): ?DateTimeImmutable;
	public function getCancelledAt(): ?DateTimeImmutable;

	public function markOpened(DateTimeImmutable $at): static;
	public function markSigned(DateTimeImmutable $at): static;
	public function cancel(DateTimeImmutable $at): static;

	/** Čeká na vyzvednutí tabletem */
	public function isPending(DateTimeImmutable $now): bool;

	/** Tablet ji právě zobrazuje - vyzvednutá, nepodepsaná a nezrušená */
	public function isOpen(): bool;

	public function getState(DateTimeImmutable $now): TabletSignRequestState;
}
