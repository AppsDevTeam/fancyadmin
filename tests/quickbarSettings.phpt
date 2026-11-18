<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Quickbar\QuickbarItem;
use ADT\FancyAdmin\Model\Quickbar\QuickbarSettings;
use Tester\Assert;

/**
 * Nastavení rychlé lišty (QuickbarSettings, README kap. 29): položky a režim „jen Menu na mobilu“ (mobileMenu).
 */

require __DIR__ . '/bootstrap.php';

/** @param array<string, mixed> $values */
function quickbarSettings(array $values): QuickbarSettings
{
	return new class ($values) extends QuickbarSettings {
		/** @param array<string, mixed> $values */
		public function __construct(private readonly array $configured)
		{
		}

		protected function getValues(): array
		{
			return $this->configured;
		}
	};
}

test('bez nastavení je lišta vypnutá a jen Menu na mobilu taky', function () {
	$settings = quickbarSettings([]);

	Assert::same([], $settings->getItems());
	Assert::false($settings->isMobileMenu());
});

test('vypnutá lišta s mobileMenu: žádné položky, jen Menu na mobilu', function () {
	$settings = quickbarSettings(['enabled' => false, 'mobileMenu' => true]);

	Assert::same([], $settings->getItems());
	Assert::true($settings->isMobileMenu());
});

test('zapnutá lišta vrací položky v pořadí návrhu, neplatná hodnota bere výchozí', function () {
	$settings = quickbarSettings(['enabled' => true, 'search' => true, 'ai' => false, 'todo' => 'ano', 'notifications' => false, 'chat' => true]);

	Assert::same([QuickbarItem::Search, QuickbarItem::Todo, QuickbarItem::Chat], $settings->getItems());
});
