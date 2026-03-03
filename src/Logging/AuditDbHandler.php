<?php

declare(strict_types=1);

namespace App\Logging;

use App\Entity\AuditEvent;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Uid\Uuid;

final class AuditDbHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $tenantSlug = $record->extra['tenant_slug'] ?? null;
        if (!is_string($tenantSlug) || '' === $tenantSlug) {
            return;
        }

        $tenant = $this->em->getRepository(Tenant::class)->findOneBy(['slug' => $tenantSlug]);
        if (!$tenant instanceof Tenant) {
            return;
        }

        $action = (string) ($record->context['action'] ?? $record->message ?? 'event');
        $resource = (string) ($record->context['resource'] ?? 'n/a');
        $status = (string) ($record->context['status'] ?? 'ok');

        $event = new AuditEvent($tenant, $action, $resource);
        $event->setStatus($status);
        $event->setBeforeData($this->normalizeContextArray($record->context['before'] ?? null));
        $event->setAfterData($this->normalizeContextArray($record->context['after'] ?? null));
        $event->setIpAddress(isset($record->extra['ip']) ? (string) $record->extra['ip'] : null);
        $event->setUserAgent(isset($record->extra['user_agent']) ? (string) $record->extra['user_agent'] : null);
        $event->setCorrelationId(isset($record->extra['uid']) ? (string) $record->extra['uid'] : null);

        $userId = $record->extra['user_id'] ?? ($record->context['user_id'] ?? null);
        if (is_string($userId) && Uuid::isValid($userId)) {
            $event->setUserId(Uuid::fromString($userId));
        }

        $this->em->persist($event);
        $this->em->flush();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeContextArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (null === $value) {
            return null;
        }

        return ['value' => $value];
    }
}
