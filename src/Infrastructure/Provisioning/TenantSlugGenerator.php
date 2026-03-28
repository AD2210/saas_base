<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

readonly class TenantSlugGenerator
{
    public function __construct(
        #[Autowire('%env(string:APP_SECRET)%')]
        private string $appSecret,
    ) {
    }

    public function generate(string $firstName, string $lastName, Uuid $adminUserUuid, ?string $fallbackBase = null): string
    {
        $base = $this->slugify(trim($firstName.' '.$lastName));
        if ('' === $base) {
            $base = $this->slugify((string) $fallbackBase);
        }

        if ('' === $base) {
            $base = 'tenant';
        }

        $suffix = substr(hash_hmac('sha256', $adminUserUuid->toRfc4122(), $this->appSecret), 0, 10);

        return sprintf('%s-%s', $base, $suffix);
    }

    private function slugify(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '-', $normalized);

        return trim($normalized, '-');
    }
}
