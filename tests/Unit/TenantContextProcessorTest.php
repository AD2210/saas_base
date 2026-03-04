<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Tenancy\TenantContext;
use App\Logging\TenantContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final class TenantContextProcessorTest extends TestCase
{
    public function testProcessorEnrichesMonologRecordWithTenantAndUser(): void
    {
        $context = new TenantContext();
        $context->set(null, 'acme');

        $user = new class implements UserInterface {
            public function getId(): string
            {
                return 'user-123';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'user-123';
            }
        };

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $processor = new TenantContextProcessor($context, $security);
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: []
        );

        $processed = $processor($record);

        self::assertInstanceOf(LogRecord::class, $processed);
        self::assertSame('acme', $processed->extra['tenant_slug'] ?? null);
        self::assertSame('user-123', $processed->extra['user_id'] ?? null);
    }

    public function testProcessorEnrichesArrayRecordForMonolog2Compatibility(): void
    {
        $context = new TenantContext();
        $context->set(null, 'tenant-legacy');

        $processor = new TenantContextProcessor($context);
        $processed = $processor([
            'message' => 'legacy',
            'extra' => [],
        ]);

        self::assertIsArray($processed);
        self::assertSame('tenant-legacy', $processed['extra']['tenant_slug'] ?? null);
        self::assertArrayNotHasKey('user_id', $processed['extra']);
    }
}
