<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303152041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_event (id UUID NOT NULL, tenant_id UUID NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID DEFAULT NULL, action VARCHAR(64) NOT NULL, resource VARCHAR(128) NOT NULL, before_data JSON DEFAULT NULL, after_data JSON DEFAULT NULL, status VARCHAR(16) NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, user_agent TEXT DEFAULT NULL, correlation_id VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3CE56E509033212A ON audit_event (tenant_id)');
        $this->addSql('COMMENT ON COLUMN audit_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_event.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_event.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN audit_event.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE contact (id UUID NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(120) NOT NULL, last_name VARCHAR(120) NOT NULL, address VARCHAR(255) NOT NULL, birth_date DATE NOT NULL, phone VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_contact_email ON contact (email)');
        $this->addSql('COMMENT ON COLUMN contact.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN contact.birth_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE demo_request (id UUID NOT NULL, contact_id UUID NOT NULL, tenant_id UUID NOT NULL, status VARCHAR(32) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, onboarding_token_hash VARCHAR(64) DEFAULT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FC3CDB5CE7A1254A ON demo_request (contact_id)');
        $this->addSql('CREATE INDEX IDX_FC3CDB5C9033212A ON demo_request (tenant_id)');
        $this->addSql('COMMENT ON COLUMN demo_request.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.contact_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.accepted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN demo_request.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tenant (id UUID NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, plan VARCHAR(32) NOT NULL, is_active BOOLEAN NOT NULL, status VARCHAR(16) NOT NULL, admin_user_uuid UUID NOT NULL, admin_email VARCHAR(180) NOT NULL, admin_first_name VARCHAR(120) NOT NULL, admin_last_name VARCHAR(120) NOT NULL, db_host VARCHAR(120) DEFAULT NULL, db_name VARCHAR(120) DEFAULT NULL, enc_db_user TEXT DEFAULT NULL, enc_db_pass TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_slug ON tenant (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_admin_email ON tenant (admin_email)');
        $this->addSql('COMMENT ON COLUMN tenant.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant.admin_user_uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenant.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenant.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tenant_migration_version (id UUID NOT NULL, tenant_id UUID NOT NULL, version VARCHAR(64) NOT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D3E97B4A9033212A ON tenant_migration_version (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_version ON tenant_migration_version (tenant_id, version)');
        $this->addSql('COMMENT ON COLUMN tenant_migration_version.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_migration_version.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_migration_version.applied_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE audit_event ADD CONSTRAINT FK_3CE56E509033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demo_request ADD CONSTRAINT FK_FC3CDB5CE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demo_request ADD CONSTRAINT FK_FC3CDB5C9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tenant_migration_version ADD CONSTRAINT FK_D3E97B4A9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE audit_event DROP CONSTRAINT FK_3CE56E509033212A');
        $this->addSql('ALTER TABLE demo_request DROP CONSTRAINT FK_FC3CDB5CE7A1254A');
        $this->addSql('ALTER TABLE demo_request DROP CONSTRAINT FK_FC3CDB5C9033212A');
        $this->addSql('ALTER TABLE tenant_migration_version DROP CONSTRAINT FK_D3E97B4A9033212A');
        $this->addSql('DROP TABLE audit_event');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE demo_request');
        $this->addSql('DROP TABLE tenant');
        $this->addSql('DROP TABLE tenant_migration_version');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
