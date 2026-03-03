<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use App\Entity\Tenant;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ChildAppAdminClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:MAIN_CHILD_APP_API_URL)%')] private readonly string $apiBaseUrl,
        #[Autowire('%env(string:MAIN_CHILD_APP_API_TOKEN)%')] private readonly string $apiToken,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
    }

    /**
     * Contract payload `tenant-admin-provisioning:v1`.
     */
    public function syncTenantAdmin(Tenant $tenant, string $plainPassword): void
    {
        $baseUrl = trim($this->apiBaseUrl);
        if ('' === $baseUrl) {
            if (in_array($this->environment, ['prod', 'beta'], true)) {
                throw new ServiceUnavailableHttpException(null, 'Child app API URL is missing.');
            }

            $this->logger->notice('child.app.admin.sync.skipped', [
                'reason' => 'MAIN_CHILD_APP_API_URL is empty',
                'tenant_uuid' => $tenant->getIdString(),
                'contract' => 'tenant-admin-provisioning:v1',
            ]);

            return;
        }

        $token = trim($this->apiToken);
        if ('' === $token) {
            throw new ServiceUnavailableHttpException(null, 'Child app API token is missing.');
        }

        $now = new \DateTimeImmutable();
        $payload = [
            'contract' => 'tenant-admin-provisioning:v1',
            'tenant_uuid' => $tenant->getIdString(),
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
            'contract' => 'tenant-admin-provisioning:v1',
            'status_code' => $statusCode,
        ]);
    }
}
