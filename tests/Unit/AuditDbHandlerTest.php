<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\AuditEvent;
use App\Entity\Tenant;
use App\Logging\AuditDbHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AuditDbHandlerTest extends TestCase
{
    public function testHandlerSkipsRecordWhenTenantSlugIsMissing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $handler = new AuditDbHandler($em);
        $handler->handle(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'audit',
            level: Level::Info,
            message: 'tenant.updated',
            context: ['action' => 'update'],
            extra: [],
        ));
    }

    public function testHandlerSkipsRecordWhenTenantCannotBeResolved(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findOneBy')->with(['slug' => 'missing-tenant'])->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->with(Tenant::class)->willReturn($repo);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $handler = new AuditDbHandler($em);
        $handler->handle(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'audit',
            level: Level::Info,
            message: 'tenant.updated',
            context: ['action' => 'update'],
            extra: ['tenant_slug' => 'missing-tenant'],
        ));
    }

    public function testHandlerPersistsAuditEventWithNormalizedContextAndMetadata(): void
    {
        $tenant = new Tenant('acme', 'Acme', 'owner@example.com', 'Ada', 'Lovelace');
        $userId = Uuid::v7()->toRfc4122();
        $persistedEvent = null;

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findOneBy')->with(['slug' => 'acme'])->willReturn($tenant);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->with(Tenant::class)->willReturn($repo);
        $em->expects($this->once())->method('persist')->with($this->callback(static function (object $entity) use (&$persistedEvent): bool {
            if (!$entity instanceof AuditEvent) {
                return false;
            }

            $persistedEvent = $entity;

            return true;
        }));
        $em->expects($this->once())->method('flush');

        $handler = new AuditDbHandler($em);
        $handler->handle(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'audit',
            level: Level::Info,
            message: 'tenant.updated',
            context: [
                'action' => 'update',
                'resource' => 'tenant',
                'status' => 'ok',
                'before' => 'legacy-string',
                'after' => ['status' => 'active'],
                'user_id' => $userId,
            ],
            extra: [
                'tenant_slug' => 'acme',
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'uid' => 'trace-123',
            ],
        ));

        self::assertInstanceOf(AuditEvent::class, $persistedEvent);
        self::assertSame('update', $persistedEvent->getAction());
        self::assertSame('tenant', $persistedEvent->getResource());
        self::assertSame('ok', $persistedEvent->getStatus());
        self::assertSame(['value' => 'legacy-string'], $persistedEvent->getBeforeData());
        self::assertSame(['status' => 'active'], $persistedEvent->getAfterData());
        self::assertSame('127.0.0.1', $persistedEvent->getIpAddress());
        self::assertSame('phpunit', $persistedEvent->getUserAgent());
        self::assertSame('trace-123', $persistedEvent->getCorrelationId());
        self::assertSame($userId, $persistedEvent->getUserId()?->toRfc4122());
    }
}
