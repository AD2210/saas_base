<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Debug\Controller\OnboardingDebugController;
use App\Entity\Tenant;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use App\Infrastructure\Provisioning\SecretBox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

final class OnboardingDebugControllerTest extends TestCase
{
    private const SECRET_BOX_KEY = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';

    public function testValidateReturnsMissingTokenErrorWhenTokenIsAbsent(): void
    {
        $controller = $this->buildController(new MockClock('2026-03-03 12:00:00'));
        $response = $controller->validate(Request::create('/debug/onboarding/validate', 'GET'));
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid', $payload['status']);
        self::assertSame('missing token', $payload['error']);
    }

    public function testValidateReturnsValidStatusForNonExpiredToken(): void
    {
        $clock = new MockClock('2026-03-03 12:00:00');
        $controller = $this->buildController($clock);
        $tenant = new Tenant('acme', 'Acme', 'owner@example.com', 'Ada', 'Lovelace');
        $token = (new OnboardingTokenManager(new SecretBox(self::SECRET_BOX_KEY), $clock))->generateToken($tenant, 3600);

        $response = $controller->validate(Request::create('/debug/onboarding/validate', 'GET', ['token' => $token]));
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('valid', $payload['status']);
        self::assertSame($tenant->getIdString(), $payload['payload']['tenant_uuid']);
    }

    public function testValidateReturnsExpiredStatusForExpiredToken(): void
    {
        $clock = new MockClock('2026-03-03 12:00:00');
        $controller = $this->buildController($clock);
        $tenant = new Tenant('acme', 'Acme', 'owner@example.com', 'Ada', 'Lovelace');
        $token = (new OnboardingTokenManager(new SecretBox(self::SECRET_BOX_KEY), $clock))->generateToken($tenant, -5);

        $response = $controller->validate(Request::create('/debug/onboarding/validate', 'GET', ['token' => $token]));
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('expired', $payload['status']);
    }

    private function buildController(MockClock $clock): OnboardingDebugController
    {
        $manager = new OnboardingTokenManager(new SecretBox(self::SECRET_BOX_KEY), $clock);

        return new OnboardingDebugController($manager);
    }
}
