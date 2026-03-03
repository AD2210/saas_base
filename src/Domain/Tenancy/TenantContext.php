<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

final class TenantContext
{
    private ?string $tenantId = null;
    private ?string $tenantSlug = null;

    public function set(?string $id, ?string $slug): void
    {
        $this->tenantId = $id;
        $this->tenantSlug = $slug;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function slug(): ?string
    {
        return $this->tenantSlug;
    }
}
