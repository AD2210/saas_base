<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use Doctrine\DBAL\Connection;

final class PostgresDbOrchestrator implements DbOrchestrator
{
    public function __construct(private readonly Connection $main)
    {
    }

    public function createDatabase(string $tenantId): array
    {
        ['db' => $db, 'user' => $user] = $this->buildIdentifiers($tenantId);
        $pass = bin2hex(random_bytes(16));

        if ($this->roleExists($user)) {
            $this->main->executeStatement(sprintf(
                'ALTER ROLE "%s" WITH PASSWORD %s',
                $user,
                $this->main->quote($pass)
            ));
        } else {
            $this->main->executeStatement(sprintf(
                'CREATE ROLE "%s" LOGIN PASSWORD %s',
                $user,
                $this->main->quote($pass)
            ));
        }

        if (!$this->databaseExists($db)) {
            $this->main->executeStatement(sprintf(
                'CREATE DATABASE "%s" OWNER "%s" ENCODING \'UTF8\'',
                $db,
                $user
            ));
        }

        return [
            'host' => 'db',
            'dbname' => $db,
            'user' => $user,
            'password' => $pass,
        ];
    }

    public function rollbackDatabase(string $tenantId): void
    {
        ['db' => $db, 'user' => $user] = $this->buildIdentifiers($tenantId);

        if ($this->databaseExists($db)) {
            $this->main->executeStatement(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :db AND pid <> pg_backend_pid()',
                ['db' => $db]
            );
            $this->main->executeStatement(sprintf('DROP DATABASE IF EXISTS "%s"', $db));
        }

        if ($this->roleExists($user)) {
            $this->main->executeStatement(sprintf('DROP ROLE IF EXISTS "%s"', $user));
        }
    }

    /**
     * @return array{db: string, user: string}
     */
    private function buildIdentifiers(string $tenantId): array
    {
        $normalizedTenantId = strtolower(str_replace('-', '_', trim($tenantId)));
        $normalizedTenantId = (string) preg_replace('/[^a-z0-9_]/', '', $normalizedTenantId);

        if ('' === $normalizedTenantId) {
            throw new \InvalidArgumentException('Invalid tenant identifier for DB provisioning.');
        }

        return [
            'db' => sprintf('db_%s', $normalizedTenantId),
            'user' => sprintf('u_%s', substr($normalizedTenantId, 0, 24)),
        ];
    }

    private function roleExists(string $role): bool
    {
        return false !== $this->main->fetchOne(
            'SELECT 1 FROM pg_catalog.pg_roles WHERE rolname = :role',
            ['role' => $role]
        );
    }

    private function databaseExists(string $database): bool
    {
        return false !== $this->main->fetchOne(
            'SELECT 1 FROM pg_database WHERE datname = :database',
            ['database' => $database]
        );
    }
}
