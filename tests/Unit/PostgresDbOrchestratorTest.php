<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Provisioning\PostgresDbOrchestrator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PostgresDbOrchestratorTest extends TestCase
{
    public function testCreateDatabaseCreatesRoleAndDatabaseWhenMissing(): void
    {
        $executedSql = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => sprintf("'%s'", $value));
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): bool {
            if (str_contains($sql, 'pg_catalog.pg_roles')) {
                return false;
            }

            if (str_contains($sql, 'pg_database')) {
                return false;
            }

            return false;
        });
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$executedSql): int {
            $executedSql[] = $sql;

            return 1;
        });

        $orchestrator = new PostgresDbOrchestrator($connection);
        $result = $orchestrator->createDatabase('11111111-2222-7333-8444-555555555555');

        self::assertSame('db', $result['host']);
        self::assertSame('db_11111111_2222_7333_8444_555555555555', $result['dbname']);
        self::assertSame('u_11111111_2222_7333_8444_', $result['user']);
        self::assertNotSame('', $result['password']);
        self::assertTrue($this->containsSql($executedSql, 'CREATE ROLE'));
        self::assertTrue($this->containsSql($executedSql, 'CREATE DATABASE'));
    }

    public function testCreateDatabaseUpdatesExistingRoleAndSkipsDatabaseCreationWhenPresent(): void
    {
        $executedSql = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => sprintf("'%s'", $value));
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): bool {
            if (str_contains($sql, 'pg_catalog.pg_roles')) {
                return true;
            }

            if (str_contains($sql, 'pg_database')) {
                return true;
            }

            return false;
        });
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$executedSql): int {
            $executedSql[] = $sql;

            return 1;
        });

        $orchestrator = new PostgresDbOrchestrator($connection);
        $orchestrator->createDatabase('22222222-3333-7444-8555-666666666666');

        self::assertTrue($this->containsSql($executedSql, 'ALTER ROLE'));
        self::assertFalse($this->containsSql($executedSql, 'CREATE DATABASE'));
    }

    public function testRollbackDatabaseDropsDatabaseAndRoleWhenTheyExist(): void
    {
        $executedSql = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): bool {
            if (str_contains($sql, 'pg_database')) {
                return true;
            }

            if (str_contains($sql, 'pg_catalog.pg_roles')) {
                return true;
            }

            return false;
        });
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$executedSql): int {
            $executedSql[] = $sql;

            return 1;
        });

        $orchestrator = new PostgresDbOrchestrator($connection);
        $orchestrator->rollbackDatabase('33333333-4444-7555-8666-777777777777');

        self::assertTrue($this->containsSql($executedSql, 'pg_terminate_backend'));
        self::assertTrue($this->containsSql($executedSql, 'DROP DATABASE IF EXISTS'));
        self::assertTrue($this->containsSql($executedSql, 'DROP ROLE IF EXISTS'));
    }

    public function testRollbackDatabaseSkipsStatementsWhenNothingExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        $orchestrator = new PostgresDbOrchestrator($connection);
        $orchestrator->rollbackDatabase('44444444-5555-7666-8777-888888888888');
    }

    public function testCreateDatabaseRejectsInvalidTenantIdentifier(): void
    {
        $connection = $this->createMock(Connection::class);
        $orchestrator = new PostgresDbOrchestrator($connection);

        $this->expectException(\InvalidArgumentException::class);
        $orchestrator->createDatabase('!!!');
    }

    /**
     * @param list<string> $executedSql
     */
    private function containsSql(array $executedSql, string $fragment): bool
    {
        foreach ($executedSql as $sql) {
            if (str_contains($sql, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
