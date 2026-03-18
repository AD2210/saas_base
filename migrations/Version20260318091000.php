<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add child app routing key to tenants and relax admin email uniqueness to app scope.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant ADD child_app_key VARCHAR(64) DEFAULT 'vault' NOT NULL");
        $this->addSql('DROP INDEX uniq_tenant_admin_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_app_admin_email ON tenant (child_app_key, admin_email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_tenant_app_admin_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_admin_email ON tenant (admin_email)');
        $this->addSql('ALTER TABLE tenant DROP child_app_key');
    }
}
