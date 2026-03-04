<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\TenantProvisionCommand;
use App\Entity\Tenant;
use App\Infrastructure\Provisioning\DbOrchestrator;
use App\Infrastructure\Provisioning\SecretBox;
use App\Infrastructure\Provisioning\TenantProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TenantProvisionCommandTest extends TestCase
{
    private const SECRET_BOX_KEY = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';

    public function testCommandReturnsSuccessWhenProvisioningWorks(): void
    {
        $command = new TenantProvisionCommand($this->buildProvisioner(false));
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([
            'company' => 'Acme Company',
            'email' => 'owner@example.com',
            'slug' => 'acme-company',
            'first-name' => 'Ada',
            'last-name' => 'Lovelace',
        ]);

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('provisioned and activated', $tester->getDisplay());
    }

    public function testCommandReturnsFailureWhenProvisioningFails(): void
    {
        $command = new TenantProvisionCommand($this->buildProvisioner(true));
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([
            'company' => 'Acme Company',
            'email' => 'owner@example.com',
            'slug' => 'acme-company',
            'first-name' => 'Ada',
            'last-name' => 'Lovelace',
        ]);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Provisioning failed:', $tester->getDisplay());
    }

    private function buildProvisioner(bool $shouldFail): TenantProvisioner
    {
        $tenantRepository = $this->createMock(EntityRepository::class);
        $tenantRepository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static function (string $className) use ($tenantRepository): EntityRepository {
            if (Tenant::class !== $className) {
                throw new \RuntimeException(sprintf('Unexpected repository "%s".', $className));
            }

            return $tenantRepository;
        });

        $dbOrchestrator = $this->createMock(DbOrchestrator::class);
        if ($shouldFail) {
            $dbOrchestrator->method('createDatabase')->willThrowException(new \RuntimeException('db failure'));
        } else {
            $dbOrchestrator->method('createDatabase')->willReturnCallback(static function (string $tenantId): array {
                return [
                    'host' => 'db',
                    'dbname' => 'db_'.str_replace('-', '_', $tenantId),
                    'user' => 'u_'.substr(str_replace('-', '_', $tenantId), 0, 24),
                    'password' => 'password',
                ];
            });
        }
        $dbOrchestrator->expects($this->any())->method('rollbackDatabase');

        return new TenantProvisioner($em, $dbOrchestrator, new SecretBox(self::SECRET_BOX_KEY), new NullLogger());
    }
}
