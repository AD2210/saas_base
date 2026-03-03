<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Tenant;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use App\Infrastructure\Provisioning\SecretBox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OnboardingTokenManagerTest extends TestCase
{
    private const KEY = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';

    public function testGenerateAndParseToken(): void
    {
        $clock = new MockClock('2026-03-02 10:00:00');
        $manager = new OnboardingTokenManager(new SecretBox(self::KEY), $clock);
        $tenant = new Tenant('acme', 'Acme', 'admin@example.com', 'Ada', 'Lovelace');

        $token = $manager->generateToken($tenant, 60);
        $payload = $manager->parseToken($token);

        self::assertSame($tenant->getIdString(), $payload['tenant_uuid']);
        self::assertSame($tenant->getAdminUserUuidString(), $payload['user_uuid']);
        self::assertSame('admin@example.com', $payload['email']);
        self::assertFalse($manager->isExpired($payload));
    }

    public function testExpiredTokenIsDetected(): void
    {
        $clock = new MockClock('2026-03-02 10:00:00');
        $manager = new OnboardingTokenManager(new SecretBox(self::KEY), $clock);
        $tenant = new Tenant('acme', 'Acme', 'admin@example.com', 'Ada', 'Lovelace');

        $token = $manager->generateToken($tenant, -10);
        $payload = $manager->parseToken($token);

        self::assertTrue($manager->isExpired($payload));
    }
}
