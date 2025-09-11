<?php
namespace App\Domain\Tenancy;
class TenantContext {
    private ?int $tenantId=null; private ?string $tenantSlug=null;
    public function set(?int $id, ?string $slug): void { $this->tenantId=$id; $this->tenantSlug=$slug; }
    public function id(): ?int { return $this->tenantId; } public function slug(): ?string { return $this->tenantSlug; }
}