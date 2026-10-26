<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Quickbar;

use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Rychlá lišta - Configuration klíč `quickbar` (JSON, zakládá ho migrace balíčku):
 * vypínač celé lišty a které položky v ní jsou. Edituje se v Nastavení.
 */
class QuickbarSettings
{
	public const string CONFIG_KEY = 'quickbar';

	public const array DEFAULTS = [
		'enabled' => false,
		'search' => true,
		'todo' => true,
		'notifications' => true,
		'chat' => true,
	];

	/** Pořadí v liště podle návrhu, ne podle pořadí klíčů v nastavení */
	private const array ORDER = [
		QuickbarItem::Search,
		QuickbarItem::Notifications,
		QuickbarItem::Todo,
		QuickbarItem::Chat,
	];

	/** @var array<string, mixed>|null Cache v rámci requestu */
	private ?array $values = null;

	public function __construct(
		private readonly ConfigurationQueryFactory $configurationQueryFactory,
	) {
	}

	/** @return list<QuickbarItem> Prázdné = lišta vypnutá */
	public function getItems(): array
	{
		if (!$this->getBool('enabled')) {
			return [];
		}

		return array_values(array_filter(self::ORDER, fn(QuickbarItem $item) => $this->getBool($item->value)));
	}

	private function getBool(string $key): bool
	{
		$value = $this->getValues()[$key] ?? null;
		return is_bool($value) ? $value : self::DEFAULTS[$key];
	}

	/** @return array<string, mixed> Chybějící nebo rozbitý řádek = výchozí hodnoty */
	private function getValues(): array
	{
		if ($this->values !== null) {
			return $this->values;
		}

		/** @var Configuration|null $configuration */
		$configuration = $this->configurationQueryFactory->create()
			->byKey(self::CONFIG_KEY)
			->fetchOneOrNull();

		try {
			$decoded = Json::decode((string) $configuration?->getValue(), forceArrays: true);
		} catch (JsonException) {
			$decoded = null;
		}

		return $this->values = is_array($decoded) ? $decoded : [];
	}
}
