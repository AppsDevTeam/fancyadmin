<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rychlá lišta: položka AI asistent v nastavení `quickbar` (formulář Nastavení ukazuje jen uložené klíče).
 */
final class Version20261027120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add AI assistant to quickbar configuration';
	}

	public function up(Schema $schema): void
	{
		$value = $this->connection->fetchOne("SELECT `value` FROM configuration WHERE `key` = 'quickbar'");
		$stored = is_string($value) ? json_decode($value, true) : null;
		if (!is_array($stored) || array_key_exists('ai', $stored)) {
			return;
		}

		// Za hledání, ať pořadí ve formuláři odpovídá liště; JSON_SET by klíče přeřadil
		$updated = [];
		foreach ($stored as $key => $item) {
			$updated[$key] = $item;
			if ($key === 'search') {
				$updated['ai'] = true;
			}
		}
		$updated['ai'] ??= true;

		$this->addSql("UPDATE configuration SET `value` = ? WHERE `key` = 'quickbar'", [json_encode($updated, JSON_PRETTY_PRINT)]);
	}

	public function down(Schema $schema): void
	{
		$this->addSql("UPDATE configuration SET `value` = JSON_REMOVE(`value`, '$.ai') WHERE `key` = 'quickbar'");
	}
}
