<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DebugRouteSecurityTest extends WebTestCase
{
    private function adminPassword(): string
    {
        return (string) (getenv('APP_ADMIN_PASSWORD') ?: ($_ENV['APP_ADMIN_PASSWORD'] ?? 'test-admin-password'));
    }

    public function testDebugRouteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/debug/onboarding/validate?token=invalid');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDebugRouteIsReachableForSuperAdmin(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'super_admin',
            'PHP_AUTH_PW' => $this->adminPassword(),
        ]);
        $client->request('GET', '/debug/onboarding/validate?token=invalid');

        self::assertResponseStatusCodeSame(400);
        self::assertJson((string) $client->getResponse()->getContent());
    }
}
