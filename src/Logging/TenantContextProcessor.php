<?php

namespace App\Logging;

use App\Domain\Tenancy\TenantContext;
use Symfony\Component\Security\Core\Security;
use Monolog\LogRecord;

final class TenantContextProcessor
{
    public function __construct(
        private TenantContext $ctx,
        private ?Security $security = null
    ) {}

    /** @param LogRecord|array $record
     *  @return LogRecord|array
     */
    public function __invoke(LogRecord|array $record): LogRecord|array
    {
        $tenantSlug = $this->ctx->slug();
        $userId = null;

        if ($this->security) {
            $user = $this->security->getUser();
            if ($user && \method_exists($user, 'getId')) {
                $userId = $user->getId();
            }
        }

        // Monolog 3: LogRecord objet
        if ($record instanceof LogRecord) {
            if ($tenantSlug !== null) {
                $record->extra['tenant_slug'] = $tenantSlug;
            }
            if ($userId !== null) {
                $record->extra['user_id'] = $userId;
            }
            return $record;
        }

        // Monolog 2: tableau associatif
        if (!\is_array($record)) {
            return $record;
        }

        $record['extra'] = $record['extra'] ?? [];
        if ($tenantSlug !== null) {
            $record['extra']['tenant_slug'] = $tenantSlug;
        }
        if ($userId !== null) {
            $record['extra']['user_id'] = $userId;
        }

        return $record;
    }
}
