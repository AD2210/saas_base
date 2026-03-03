<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminAccessTest extends WebTestCase
{
    private function adminPassword(): string
    {
        return (string) (getenv('APP_ADMIN_PASSWORD') ?: ($_ENV['APP_ADMIN_PASSWORD'] ?? 'test-admin-password'));
    }

    public function testAdminRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSuperAdminCanAccessDashboard(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'super_admin',
            'PHP_AUTH_PW' => $this->adminPassword(),
        ]);
        $client->request('GET', '/admin');

        self::assertResponseRedirects();
    }

    public function testSuperAdminCanAccessOpsPage(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'super_admin',
            'PHP_AUTH_PW' => $this->adminPassword(),
        ]);
        $client->request('GET', '/admin/ops');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Monitoring', (string) $client->getResponse()->getContent());
    }
}
