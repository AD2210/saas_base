<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ChildApp\ChildAppCatalog;
use App\Entity\Tenant;
use App\Infrastructure\Provisioning\ChildAppAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class ChildAppAdminClientTest extends TestCase
{
    public function testSyncTenantAdminSkipsWhenApiUrlIsMissing(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 201]),
        ]);
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog(''), 'dev');

        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');

        self::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testSyncTenantAdminFailsWhenTokenIsMissing(): void
    {
        $httpClient = new MockHttpClient();
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog('https://child.local', ''), 'dev');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    public function testSyncTenantAdminSucceedsWith2xxResponse(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            TestCase::assertSame('POST', $method);
            TestCase::assertSame('https://child.local/internal/provisioning/tenant-admin', $url);
            TestCase::assertContains('Authorization: Bearer token', $options['headers']);
            $payload = json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            TestCase::assertSame('tenant-admin-provisioning:v1', $payload['contract']);
            TestCase::assertSame('vault', $payload['child_app_key']);
            TestCase::assertSame('Client Secrets Vault', $payload['child_app_name']);
            TestCase::assertSame('tenant-a', $payload['tenant_slug']);

            return new MockResponse('{}', ['http_code' => 202]);
        });
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog('https://child.local', 'token'), 'dev');

        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');

        self::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSyncTenantAdminFailsWithoutUrlInProd(): void
    {
        $httpClient = new MockHttpClient();
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog('', 'token'), 'prod');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    public function testSyncTenantAdminFailsWhenEndpointReturnsNon2xx(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 503]),
        ]);
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog('https://child.local', 'token'), 'dev');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    public function testSyncTenantAdminFailsWhenTransportLayerIsUnreachable(): void
    {
        $httpClient = new MockHttpClient(static function (): never {
            throw new TransportException('network unreachable');
        });
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), $this->childAppCatalog('https://child.local', 'token'), 'dev');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    private function createTenant(): Tenant
    {
        $tenant = new Tenant('tenant-a', 'Tenant A', 'owner@example.com', 'Ada', 'Lovelace', null, 'vault');
        $tenant->markProvisioningCreated();

        return $tenant;
    }

    private function childAppCatalog(string $apiUrl, string $apiToken = 'token'): ChildAppCatalog
    {
        return new ChildAppCatalog([
            'default_key' => 'vault',
            'apps' => [
                'vault' => [
                    'name' => 'Client Secrets Vault',
                    'api_url' => $apiUrl,
                    'login_url' => 'https://vault.example/login',
                    'api_token' => $apiToken,
                ],
            ],
        ]);
    }
}
