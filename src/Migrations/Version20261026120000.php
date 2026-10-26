<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nastavení rychlé lišty (QuickbarSettings) - výchozí vypnutá, aby se projektu po aktualizaci nic neobjevilo.
 */
final class Version20261026120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add quickbar configuration entry';
	}

	public function up(Schema $schema): void
	{
		$this->addSql("INSERT INTO configuration (`key`, name, `type`, `value`, updated_at)
			SELECT 'quickbar', 'Rychlá lišta dole na obrazovce', 'json', '{\"enabled\":false,\"search\":true,\"todo\":true,\"notifications\":true,\"chat\":true}', NOW()
			FROM DUAL
			WHERE NOT EXISTS (SELECT 1 FROM configuration WHERE `key` = 'quickbar')");
	}

	public function down(Schema $schema): void
	{
		$this->addSql("DELETE FROM configuration WHERE `key` = 'quickbar'");
	}
}
