<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version202509110843 extends AbstractMigration {
    public function getDescription(): string { return 'Main DB: tenant, tenant_migration_version, audit_event tables'; }
    public function up(Schema $schema): void {
        $this->addSql('CREATE TABLE tenant (id BIGSERIAL NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, plan VARCHAR(32) NOT NULL, is_active BOOLEAN NOT NULL, db_host VARCHAR(120) NOT NULL, db_name VARCHAR(120) NOT NULL, enc_db_user TEXT NOT NULL, enc_db_pass TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_slug ON tenant (slug)');
        $this->addSql('CREATE TABLE tenant_migration_version (id BIGSERIAL NOT NULL, tenant_id BIGINT NOT NULL, version VARCHAR(64) NOT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_version ON tenant_migration_version (tenant_id, version)');
        $this->addSql('ALTER TABLE tenant_migration_version ADD CONSTRAINT FK_TMV_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE audit_event (id BIGSERIAL NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id BIGINT NOT NULL, user_id VARCHAR(64) DEFAULT NULL, action VARCHAR(64) NOT NULL, resource VARCHAR(128) NOT NULL, before JSONB DEFAULT NULL, after JSONB DEFAULT NULL, status VARCHAR(16) NOT NULL, ip VARCHAR(64) DEFAULT NULL, ua TEXT DEFAULT NULL, corr_id VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE audit_event ADD CONSTRAINT FK_AUDIT_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void {
        $this->addSql('ALTER TABLE audit_event DROP CONSTRAINT FK_AUDIT_TENANT');
        $this->addSql('ALTER TABLE tenant_migration_version DROP CONSTRAINT FK_TMV_TENANT');
        $this->addSql('DROP TABLE audit_event');
        $this->addSql('DROP TABLE tenant_migration_version');
        $this->addSql('DROP TABLE tenant');
    }
}