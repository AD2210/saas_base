<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Tenant;
use App\Infrastructure\Provisioning\DbOrchestrator;
use App\Infrastructure\Provisioning\SecretBox;
use App\Infrastructure\Provisioning\TenantProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TenantProvisionerTest extends TestCase
{
    public function testProvisionDatabaseSucceedsOnFirstAttempt(): void
    {
        $db = $this->createMock(DbOrchestrator::class);
        $db->expects($this->once())
            ->method('createDatabase')
            ->willReturn([
                'host' => 'db',
                'dbname' => 'db_tenant',
                'user' => 'u_tenant',
                'password' => 'password',
            ]);
        $db->expects($this->never())->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $this->createTenant();

        $provisioner->provisionDatabase($tenant);

        self::assertSame(Tenant::STATUS_CREATED, $tenant->getStatus());
        self::assertTrue($tenant->isActive());
        self::assertTrue($tenant->hasProvisionedDatabase());
    }

    public function testProvisionDatabaseRetriesAndSucceedsOnThirdAttempt(): void
    {
        $db = $this->createMock(DbOrchestrator::class);
        $attempt = 0;
        $db->expects($this->exactly(3))
            ->method('createDatabase')
            ->willReturnCallback(static function () use (&$attempt): array {
                ++$attempt;
                if ($attempt < 3) {
                    throw new \RuntimeException('temporary failure');
                }

                return [
                    'host' => 'db',
                    'dbname' => 'db_tenant',
                    'user' => 'u_tenant',
                    'password' => 'password',
                ];
            });
        $db->expects($this->exactly(2))->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $this->createTenant();

        $provisioner->provisionDatabase($tenant);

        self::assertSame(Tenant::STATUS_CREATED, $tenant->getStatus());
        self::assertTrue($tenant->isActive());
        self::assertTrue($tenant->hasProvisionedDatabase());
    }

    public function testProvisionDatabaseFailsAfterThreeAttemptsAndMarksTenantFailed(): void
    {
        $db = $this->createMock(DbOrchestrator::class);
        $db->expects($this->exactly(3))
            ->method('createDatabase')
            ->willThrowException(new \RuntimeException('database unavailable'));
        $db->expects($this->exactly(3))->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $this->createTenant();
        $tenant->setDbHost('db')
            ->setDbName('db_old')
            ->setEncDbUser('encrypted_user')
            ->setEncDbPass('encrypted_pass');

        try {
            $provisioner->provisionDatabase($tenant);
            self::fail('Expected provisioning exception was not thrown.');
        } catch (\RuntimeException) {
            self::assertSame(Tenant::STATUS_FAILED, $tenant->getStatus());
            self::assertFalse($tenant->isActive());
            self::assertFalse($tenant->hasProvisionedDatabase());
        }
    }

    public function testProvisionDatabaseIsIdempotentWhenTenantIsAlreadyCreated(): void
    {
        $db = $this->createMock(DbOrchestrator::class);
        $db->expects($this->never())->method('createDatabase');
        $db->expects($this->never())->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $this->createTenant();
        $tenant->setDbHost('db')
            ->setDbName('db_tenant')
            ->setEncDbUser('encrypted_user')
            ->setEncDbPass('encrypted_pass');
        $tenant->markProvisioningCreated();

        $provisioner->provisionDatabase($tenant);

        self::assertSame(Tenant::STATUS_CREATED, $tenant->getStatus());
        self::assertTrue($tenant->isActive());
        self::assertTrue($tenant->hasProvisionedDatabase());
    }

    private function buildProvisioner(EntityManagerInterface $em, DbOrchestrator $db): TenantProvisioner
    {
        $crypto = new SecretBox(str_repeat('a', 64));
        $logger = $this->createMock(LoggerInterface::class);

        return new TenantProvisioner($em, $db, $crypto, $logger);
    }

    private function createTenant(): Tenant
    {
        return new Tenant('tenant-slug', 'Tenant Co', 'owner@example.com', 'John', 'Doe');
    }
}
