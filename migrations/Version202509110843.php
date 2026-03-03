<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202509110843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Main DB V1: tenant/contact/demo_request + audit + tenant migration tracking (UUID-based)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant (
                id UUID NOT NULL,
                slug VARCHAR(80) NOT NULL,
                name VARCHAR(160) NOT NULL,
                plan VARCHAR(32) NOT NULL,
                is_active BOOLEAN NOT NULL,
                status VARCHAR(16) NOT NULL,
                admin_user_uuid UUID NOT NULL,
                admin_email VARCHAR(180) NOT NULL,
                admin_first_name VARCHAR(120) NOT NULL,
                admin_last_name VARCHAR(120) NOT NULL,
                db_host VARCHAR(120) DEFAULT NULL,
                db_name VARCHAR(120) DEFAULT NULL,
                enc_db_user TEXT DEFAULT NULL,
                enc_db_pass TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_slug ON tenant (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_admin_email ON tenant (admin_email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE contact (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                first_name VARCHAR(120) NOT NULL,
                last_name VARCHAR(120) NOT NULL,
                address VARCHAR(255) NOT NULL,
                birth_date DATE NOT NULL,
                phone VARCHAR(32) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_contact_email ON contact (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE demo_request (
                id UUID NOT NULL,
                contact_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                status VARCHAR(32) NOT NULL,
                requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                onboarding_token_hash VARCHAR(64) DEFAULT NULL,
                accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_demo_request_contact ON demo_request (contact_id)');
        $this->addSql('CREATE INDEX idx_demo_request_tenant ON demo_request (tenant_id)');
        $this->addSql('ALTER TABLE demo_request ADD CONSTRAINT fk_demo_request_contact FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demo_request ADD CONSTRAINT fk_demo_request_tenant FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_migration_version (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                version VARCHAR(64) NOT NULL,
                applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_version ON tenant_migration_version (tenant_id, version)');
        $this->addSql('ALTER TABLE tenant_migration_version ADD CONSTRAINT fk_tmv_tenant FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE audit_event (
                id UUID NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                resource VARCHAR(128) NOT NULL,
                before_data JSONB DEFAULT NULL,
                after_data JSONB DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                correlation_id VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_event_tenant ON audit_event (tenant_id)');
        $this->addSql('ALTER TABLE audit_event ADD CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_event DROP CONSTRAINT fk_audit_tenant');
        $this->addSql('ALTER TABLE tenant_migration_version DROP CONSTRAINT fk_tmv_tenant');
        $this->addSql('ALTER TABLE demo_request DROP CONSTRAINT fk_demo_request_contact');
        $this->addSql('ALTER TABLE demo_request DROP CONSTRAINT fk_demo_request_tenant');
        $this->addSql('DROP TABLE audit_event');
        $this->addSql('DROP TABLE tenant_migration_version');
        $this->addSql('DROP TABLE demo_request');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE tenant');
    }
}
