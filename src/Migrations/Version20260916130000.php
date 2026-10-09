<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Politika hesel se presouva z ciselniku `configuration` na roli, takze si ji lze nastavit
 * na kterekoliv z nich, ne jen na dvou pevnych urovnich.
 *
 * Tahle migrace pridava jen sloupce. Prenos hodnot ze stavajicich radku a jejich smazani
 * dela projekt: ktera role je "backoffice" pozna podle sveho ACL resource, jehoz nazev si
 * projekt konfiguruje sam (backofficeAclResource), takze balicek ho neuhodne.
 */
final class Version20260916130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password policy columns to acl_role';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acl_role
            ADD password_policy_enabled TINYINT(1) DEFAULT 0 NOT NULL,
            ADD password_min_length INT DEFAULT NULL,
            ADD password_require_uppercase TINYINT(1) DEFAULT 0 NOT NULL,
            ADD password_require_lowercase TINYINT(1) DEFAULT 0 NOT NULL,
            ADD password_require_digit TINYINT(1) DEFAULT 0 NOT NULL,
            ADD password_require_special_char TINYINT(1) DEFAULT 0 NOT NULL,
            ADD session_expiration_minutes INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acl_role
            DROP password_policy_enabled,
            DROP password_min_length,
            DROP password_require_uppercase,
            DROP password_require_lowercase,
            DROP password_require_digit,
            DROP password_require_special_char,
            DROP session_expiration_minutes');
    }
}
