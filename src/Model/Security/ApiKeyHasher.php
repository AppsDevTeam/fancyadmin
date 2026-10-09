<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use Nette\Utils\Random;

/**
 * Generování a hashování API klíčů.
 *
 * V databázi je uložený jen SHA-256 otisk (sloupec `key`), čitelný klíč se zobrazí
 * jednou při vytvoření. Ověření = spočítat otisk přijatého klíče a najít shodu.
 */
final class ApiKeyHasher
{
	public const int RAW_KEY_LENGTH = 32;

	private const string RAW_KEY_CHARLIST = '0-9a-zA-Z';

	public static function generateRawKey(): string
	{
		return Random::generate(self::RAW_KEY_LENGTH, self::RAW_KEY_CHARLIST);
	}

	public static function hash(string $rawKey): string
	{
		return hash('sha256', $rawKey);
	}
}
