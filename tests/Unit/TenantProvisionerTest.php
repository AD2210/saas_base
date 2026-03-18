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
    public function testCreateTenantAccountPersistsTenantWithNormalizedSlugAndEmail(): void
    {
        $tenantRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $tenantRepo->expects($this->once())->method('findOneBy')->with(['slug' => 'acme-company'])->willReturn(null);

        $db = $this->createMock(DbOrchestrator::class);
        $db->expects($this->never())->method('createDatabase');
        $db->expects($this->never())->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->with(Tenant::class)->willReturn($tenantRepo);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Tenant::class));
        $em->expects($this->once())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $provisioner->createTenantAccount(' OWNER@EXAMPLE.COM ', ' Acme Company ', ' Ada ', ' Lovelace ', null, 'ops');

        self::assertSame('acme-company', $tenant->getSlug());
        self::assertSame('ops', $tenant->getChildAppKey());
        self::assertSame('owner@example.com', $tenant->getAdminEmail());
        self::assertSame('Ada', $tenant->getAdminFirstName());
        self::assertSame('Lovelace', $tenant->getAdminLastName());
        self::assertSame(Tenant::STATUS_REQUESTED, $tenant->getStatus());
    }

    public function testCreateTenantAccountAppendsSuffixWhenSlugAlreadyExists(): void
    {
        $existingTenant = new Tenant('acme-company', 'Acme', 'existing@example.com', 'Existing', 'Admin');
        $tenantRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $tenantRepo->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($existingTenant): ?Tenant {
                if (($criteria['slug'] ?? null) === 'acme-company') {
                    return $existingTenant;
                }

                return null;
            });

        $db = $this->createMock(DbOrchestrator::class);
        $db->expects($this->never())->method('createDatabase');
        $db->expects($this->never())->method('rollbackDatabase');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('getRepository')->with(Tenant::class)->willReturn($tenantRepo);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Tenant::class));
        $em->expects($this->once())->method('flush');

        $provisioner = $this->buildProvisioner($em, $db);
        $tenant = $provisioner->createTenantAccount('owner@example.com', 'Acme Company');

        self::assertSame('acme-company-2', $tenant->getSlug());
    }

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
