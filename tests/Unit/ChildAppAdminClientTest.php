<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Tenant;
use App\Infrastructure\Provisioning\ChildAppAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), '', '', 'dev');

        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');

        self::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testSyncTenantAdminFailsWhenTokenIsMissing(): void
    {
        $httpClient = new MockHttpClient();
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), 'https://child.local', '', 'dev');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    public function testSyncTenantAdminSucceedsWith2xxResponse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 202]),
        ]);
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), 'https://child.local', 'token', 'dev');

        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');

        self::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSyncTenantAdminFailsWithoutUrlInProd(): void
    {
        $httpClient = new MockHttpClient();
        $client = new ChildAppAdminClient($httpClient, new NullLogger(), '', 'token', 'prod');

        $this->expectException(ServiceUnavailableHttpException::class);
        $client->syncTenantAdmin($this->createTenant(), 'StrongPassw0rd!');
    }

    private function createTenant(): Tenant
    {
        $tenant = new Tenant('tenant-a', 'Tenant A', 'owner@example.com', 'Ada', 'Lovelace');
        $tenant->markProvisioningCreated();

        return $tenant;
    }
}
