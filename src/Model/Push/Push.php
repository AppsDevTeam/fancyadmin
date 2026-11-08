<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Push;

/**
 * Push změn do otevřených stránek (docs/push.md). Bez hubu (NullPush) se nic neposílá a stránky
 * dál pollují - push jen zkracuje, za jak dlouho se změna objeví.
 */
interface Push
{
	/**
	 * Oznámí změnu odběratelům topiku (např. „calendar", „user/5"). Selhání se jen zaloguje -
	 * stránka se dozví o změně při dalším pollingu.
	 *
	 * @param array<string, mixed> $data drobná data pro stránku (co se změnilo), ne citlivý obsah
	 */
	public function publish(string $topic, array $data = []): void;

	/**
	 * Odběr pro prohlížeč: adresa hubu, plné názvy topiků a token, který dovolí odebírat jen je.
	 * Null = push není (stránka polluje).
	 *
	 * @param list<string> $topics
	 * @return array{url: string, topics: list<string>, token: string}|null
	 */
	public function subscription(array $topics): ?array;
}
