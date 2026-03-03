<?php

declare(strict_types=1);

namespace App\Logging;

use App\Domain\Tenancy\TenantContext;
use Monolog\LogRecord;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class TenantContextProcessor
{
    public function __construct(
        private TenantContext $ctx,
        private ?Security $security = null,
    ) {
    }

    /**
     * @param LogRecord|array<string, mixed> $record
     *
     * @return LogRecord|array<string, mixed>
     */
    public function __invoke(LogRecord|array $record): LogRecord|array
    {
        $tenantSlug = $this->ctx->slug();
        $userId = null;

        if ($this->security) {
            $user = $this->security->getUser();
            if ($user && \method_exists($user, 'getId')) {
                $userId = (string) $user->getId();
            }
        }

        // Monolog 3: LogRecord objet
        if ($record instanceof LogRecord) {
            if (null !== $tenantSlug) {
                $record->extra['tenant_slug'] = $tenantSlug;
            }
            if (null !== $userId) {
                $record->extra['user_id'] = $userId;
            }

            return $record;
        }

        // Monolog 2: tableau associatif
        $record['extra'] = $record['extra'] ?? [];
        if (null !== $tenantSlug) {
            $record['extra']['tenant_slug'] = $tenantSlug;
        }
        if (null !== $userId) {
            $record['extra']['user_id'] = $userId;
        }

        return $record;
    }
}
