<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use App\ChildApp\ChildAppCatalog;
use App\Entity\Tenant;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ChildAppAdminClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ChildAppCatalog $childAppCatalog,
        private readonly string $environment,
    ) {
    }

    /**
     * Contract payload `tenant-admin-provisioning:v1`.
     */
    public function syncTenantAdmin(Tenant $tenant, string $plainPassword): void
    {
        $childApp = $this->childAppCatalog->resolve($tenant->getChildAppKey());
        $baseUrl = $childApp->getApiUrl();
        if ('' === $baseUrl) {
            if (in_array($this->environment, ['prod', 'beta'], true)) {
                throw new ServiceUnavailableHttpException(null, sprintf('Child app API URL is missing for "%s".', $tenant->getChildAppKey()));
            }

            $this->logger->notice('child.app.admin.sync.skipped', [
                'reason' => 'child app api url is empty',
                'child_app_key' => $tenant->getChildAppKey(),
                'tenant_uuid' => $tenant->getIdString(),
                'contract' => 'tenant-admin-provisioning:v1',
            ]);

            return;
        }

        $token = $childApp->getApiToken();
        if ('' === $token) {
            throw new ServiceUnavailableHttpException(null, sprintf('Child app API token is missing for "%s".', $tenant->getChildAppKey()));
        }

        $now = new \DateTimeImmutable();
        $payload = [
            'contract' => 'tenant-admin-provisioning:v1',
            'child_app_key' => $tenant->getChildAppKey(),
            'child_app_name' => $childApp->getName(),
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'tenant_name' => $tenant->getName(),
            'user_uuid' => $tenant->getAdminUserUuidString(),
            'email' => $tenant->getAdminEmail(),
            'first_name' => $tenant->getAdminFirstName(),
            'last_name' => $tenant->getAdminLastName(),
            'status' => $tenant->isActive() ? 'active' : 'inactive',
            'created_at' => $now->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->format(\DateTimeInterface::ATOM),
            'password' => $plainPassword,
        ];

        try {
            $response = $this->httpClient->request('POST', rtrim($baseUrl, '/').'/internal/provisioning/tenant-admin', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw new ServiceUnavailableHttpException(30, 'Child app provisioning endpoint is unreachable.', $exception);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ServiceUnavailableHttpException(30, sprintf('Child app provisioning endpoint returned HTTP %d.', $statusCode));
        }

        $this->logger->info('child.app.admin.sync.succeeded', [
            'tenant_uuid' => $tenant->getIdString(),
            'child_app_key' => $tenant->getChildAppKey(),
            'contract' => 'tenant-admin-provisioning:v1',
            'status_code' => $statusCode,
        ]);
    }
}
