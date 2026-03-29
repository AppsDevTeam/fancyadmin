<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password policy configuration entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO configuration (`key`, name, `type`, `value`, updated_at) VALUES ('password.policy.backoffice', 'Politika hesel - Backoffice', 'json', '{\"enabled\":false,\"minLength\":12,\"requireUppercase\":true,\"requireLowercase\":true,\"requireDigit\":true,\"requireSpecialChar\":true}', NOW())");
        $this->addSql("INSERT INTO configuration (`key`, name, `type`, `value`, updated_at) VALUES ('password.policy.admin', 'Politika hesel - Administrátor', 'json', '{\"enabled\":false,\"minLength\":17,\"requireUppercase\":true,\"requireLowercase\":true,\"requireDigit\":true,\"requireSpecialChar\":true}', NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM configuration WHERE `key` IN ('password.policy.backoffice', 'password.policy.admin')");
    }
}
