<?php
namespace App\Logging;
use App\Entity\AuditEvent;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class AuditDbHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        int|string|Level $level = Level::Info,
        bool $bubble = true
    ) { parent::__construct($level, $bubble); }

    protected function write(LogRecord $record): void
    {
        $tenantSlug = $record->extra['tenant_slug'] ?? null;
        if (!$tenantSlug) return;
        $tenant = $this->em->getRepository(Tenant::class)->findOneBy(['slug'=>$tenantSlug]);
        if (!$tenant) return;

        $action   = (string)($record->context['action'] ?? $record->message ?? 'event');
        $resource = (string)($record->context['resource'] ?? 'n/a');
        $status   = (string)($record->context['status'] ?? 'ok');

        $evt = new AuditEvent($tenant, $action, $resource);

        $before = $record->context['before'] ?? null;
        $after  = $record->context['after']  ?? null;

        $evtBefore = is_array($before) ? $before : ($before === null ? null : ['value'=>$before]);
        $evtAfter  = is_array($after)  ? $after  : ($after  === null ? null  : ['value'=>$after]);

        $ip     = $record->extra['ip'] ?? null;
        $ua     = $record->extra['user_agent'] ?? ($record->extra['ua'] ?? null);
        $corrId = $record->extra['uid'] ?? ($record->extra['request_id'] ?? null);
        $userId = $record->extra['user_id'] ?? ($record->context['user_id'] ?? null);

        $rp = new \ReflectionProperty(AuditEvent::class, 'before'); $rp->setAccessible(true); $rp->setValue($evt, $evtBefore);
        $rp = new \ReflectionProperty(AuditEvent::class, 'after');  $rp->setAccessible(true); $rp->setValue($evt, $evtAfter);
        $rp = new \ReflectionProperty(AuditEvent::class, 'status'); $rp->setAccessible(true); $rp->setValue($evt, $status);
        if ($ip !== null) { $rp = new \ReflectionProperty(AuditEvent::class, 'ip'); $rp->setAccessible(true); $rp->setValue($evt, (string)$ip); }
        if ($ua !== null) { $rp = new \ReflectionProperty(AuditEvent::class, 'ua'); $rp->setAccessible(true); $rp->setValue($evt, (string)$ua); }
        if ($corrId !== null) { $rp = new \ReflectionProperty(AuditEvent::class, 'corrId'); $rp->setAccessible(true); $rp->setValue($evt, (string)$corrId); }
        if ($userId !== null) { $rp = new \ReflectionProperty(AuditEvent::class, 'userId'); $rp->setAccessible(true); $rp->setValue($evt, (string)$userId); }

        $this->em->persist($evt);
        $this->em->flush();
    }
}
