<?php
namespace App\Logging;
use App\Domain\Tenancy\TenantContext;
use Symfony\Component\Security\Core\Security;
final class TenantContextProcessor {
    public function __construct(private TenantContext $ctx, private ?Security $security=null) {}
    public function __invoke(array $record): array {
        $record['extra']['tenant_slug'] = $this->ctx->slug();
        if ($this->security) {
            $user = $this->security->getUser();
            if ($user && method_exists($user,'getId')) { $record['extra']['user_id'] = $user->getId(); }
        }
        return $record;
    }
}