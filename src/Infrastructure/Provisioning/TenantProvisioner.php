<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class TenantProvisioner
{
    private const MAX_PROVISION_ATTEMPTS = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DbOrchestrator $db,
        private readonly SecretBox $crypto,
        private readonly LoggerInterface $logger,
        private readonly TenantSlugGenerator $tenantSlugGenerator,
    ) {
    }

    public function createTenantAccount(
        string $email,
        string $company,
        string $firstName = 'Tenant',
        string $lastName = 'Admin',
        ?string $slug = null,
        string $childAppKey = 'vault',
    ): Tenant {
        $adminUserUuid = Uuid::v7();
        $baseSlug = $slug ?? $this->tenantSlugGenerator->generate($firstName, $lastName, $adminUserUuid, $company);
        $tenantSlug = $this->generateUniqueSlug($baseSlug);

        $tenant = new Tenant(
            slug: $tenantSlug,
            name: $company,
            adminEmail: $email,
            adminFirstName: $firstName,
            adminLastName: $lastName,
            adminUserUuid: $adminUserUuid,
            childAppKey: $childAppKey,
        );

        $this->em->persist($tenant);
        $this->em->flush();

        $this->logger->info('tenant.account.created', [
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'child_app_key' => $tenant->getChildAppKey(),
            'email' => $email,
            'status' => $tenant->getStatus(),
        ]);

        return $tenant;
    }

    public function provisionDatabase(Tenant $tenant): void
    {
        if (Tenant::STATUS_CREATED === $tenant->getStatus() && $tenant->hasProvisionedDatabase()) {
            $this->logger->info('tenant.db.provision.skipped', [
                'tenant_uuid' => $tenant->getIdString(),
                'tenant_slug' => $tenant->getSlug(),
                'status' => $tenant->getStatus(),
            ]);

            return;
        }

        $lastException = new \RuntimeException('Unknown provisioning failure.');
        for ($attempt = 1; $attempt <= self::MAX_PROVISION_ATTEMPTS; ++$attempt) {
            try {
                $creds = $this->db->createDatabase($tenant->getIdString());

                $tenant
                    ->setDbHost($creds['host'])
                    ->setDbName($creds['dbname'])
                    ->setEncDbUser($this->crypto->encrypt($creds['user']))
                    ->setEncDbPass($this->crypto->encrypt($creds['password']));
                $tenant->markProvisioningCreated();

                $this->em->flush();

                $this->logger->info('tenant.db.provisioned', [
                    'tenant_uuid' => $tenant->getIdString(),
                    'tenant_slug' => $tenant->getSlug(),
                    'db_name' => $tenant->getDbName(),
                    'status' => $tenant->getStatus(),
                    'attempt' => $attempt,
                ]);

                return;
            } catch (\Throwable $exception) {
                $lastException = $exception;

                $this->logger->warning('tenant.db.provision.retry', [
                    'tenant_uuid' => $tenant->getIdString(),
                    'tenant_slug' => $tenant->getSlug(),
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_PROVISION_ATTEMPTS,
                    'error' => $exception->getMessage(),
                ]);

                $this->rollbackProvisioningResources($tenant, $attempt, $exception);
            }
        }

        $tenant->resetProvisioningCredentials();
        $tenant->markProvisioningFailed();
        $this->em->flush();

        $this->logger->error('tenant.db.provision.failed', [
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'max_attempts' => self::MAX_PROVISION_ATTEMPTS,
            'error' => $lastException->getMessage(),
        ]);

        throw new \RuntimeException(sprintf('Tenant %s database provisioning failed after %d attempts.', $tenant->getIdString(), self::MAX_PROVISION_ATTEMPTS), 0, $lastException);
    }

    private function generateUniqueSlug(string $candidate): string
    {
        $base = (string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($candidate)));
        $base = trim($base, '-');
        $base = '' === $base ? 'tenant' : $base;

        $slug = $base;
        $suffix = 2;

        while (null !== $this->em->getRepository(Tenant::class)->findOneBy(['slug' => $slug])) {
            $slug = sprintf('%s-%d', $base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    private function rollbackProvisioningResources(Tenant $tenant, int $attempt, \Throwable $exception): void
    {
        try {
            $this->db->rollbackDatabase($tenant->getIdString());

            $this->logger->info('tenant.db.rollback.completed', [
                'tenant_uuid' => $tenant->getIdString(),
                'tenant_slug' => $tenant->getSlug(),
                'attempt' => $attempt,
            ]);
        } catch (\Throwable $rollbackException) {
            $this->logger->warning('tenant.db.rollback.failed', [
                'tenant_uuid' => $tenant->getIdString(),
                'tenant_slug' => $tenant->getSlug(),
                'attempt' => $attempt,
                'provision_error' => $exception->getMessage(),
                'rollback_error' => $rollbackException->getMessage(),
            ]);
        }
    }
}
