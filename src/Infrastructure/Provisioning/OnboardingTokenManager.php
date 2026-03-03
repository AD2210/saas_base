<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use App\Entity\Tenant;
use Symfony\Component\Clock\ClockInterface;

final readonly class OnboardingTokenManager
{
    public function __construct(
        private SecretBox $crypto,
        private ClockInterface $clock,
    ) {
    }

    public function generateToken(Tenant $tenant, int $ttlSeconds = 86400): string
    {
        $expiresAt = $this->clock->now()->modify(sprintf('+%d seconds', $ttlSeconds));
        $payload = [
            'tenant_uuid' => $tenant->getIdString(),
            'user_uuid' => $tenant->getAdminUserUuidString(),
            'email' => $tenant->getAdminEmail(),
            'exp' => $expiresAt->getTimestamp(),
        ];

        return $this->crypto->encrypt((string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{tenant_uuid: string, user_uuid: string, email: string, exp: int}
     */
    public function parseToken(string $token): array
    {
        $decoded = json_decode($this->crypto->decrypt($token), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid onboarding token payload.');
        }

        foreach (['tenant_uuid', 'user_uuid', 'email', 'exp'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $decoded)) {
                throw new \RuntimeException(sprintf('Missing key "%s" in onboarding token.', $requiredKey));
            }
        }

        return [
            'tenant_uuid' => (string) $decoded['tenant_uuid'],
            'user_uuid' => (string) $decoded['user_uuid'],
            'email' => (string) $decoded['email'],
            'exp' => (int) $decoded['exp'],
        ];
    }

    /**
     * @param array{tenant_uuid: string, user_uuid: string, email: string, exp: int} $payload
     */
    public function isExpired(array $payload): bool
    {
        return $payload['exp'] < $this->clock->now()->getTimestamp();
    }
}
