<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\AuditEvent;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Entity\Tenant;
use App\Entity\TenantMigrationVersion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EntityLifecycleTest extends TestCase
{
    public function testTenantStateTransitionsAndSharedTraits(): void
    {
        $tenant = new Tenant('  ACME Company  ', '  Acme Inc  ', ' OWNER@EXAMPLE.COM ', ' Ada ', ' Lovelace ');

        self::assertSame('acme-company', $tenant->getSlug());
        self::assertSame('Acme Inc', $tenant->getName());
        self::assertSame('owner@example.com', $tenant->getAdminEmail());
        self::assertFalse($tenant->isActive());
        self::assertSame(Tenant::STATUS_REQUESTED, $tenant->getStatus());
        self::assertFalse($tenant->hasProvisionedDatabase());

        $tenant->setDbHost(' db ')
            ->setDbName(' db_name ')
            ->setEncDbUser('encrypted-user')
            ->setEncDbPass('encrypted-pass');
        self::assertTrue($tenant->hasProvisionedDatabase());
        self::assertSame('db', $tenant->getDbHost());
        self::assertSame('db_name', $tenant->getDbName());

        $tenant->markProvisioningCreated();
        self::assertTrue($tenant->isActive());
        self::assertSame(Tenant::STATUS_CREATED, $tenant->getStatus());

        $tenant->resetProvisioningCredentials();
        self::assertFalse($tenant->hasProvisionedDatabase());

        $tenant->markProvisioningFailed();
        self::assertFalse($tenant->isActive());
        self::assertSame(Tenant::STATUS_FAILED, $tenant->getStatus());

        $tenant->cancel();
        self::assertFalse($tenant->isActive());
        self::assertSame(Tenant::STATUS_CANCELLED, $tenant->getStatus());

        $tenant->initializeUuid();
        self::assertTrue(Uuid::isValid($tenant->getIdString()));

        $tenant->initializeTimestamps();
        $firstUpdatedAt = $tenant->getUpdatedAt();
        $tenant->updateTimestamp();
        self::assertGreaterThanOrEqual($firstUpdatedAt->getTimestamp(), $tenant->getUpdatedAt()->getTimestamp());

        self::assertFalse($tenant->isDeleted());
        $tenant->softDelete();
        self::assertTrue($tenant->isDeleted());
        self::assertNotNull($tenant->getDeletedAt());
        $tenant->restore();
        self::assertFalse($tenant->isDeleted());
    }

    public function testDemoRequestLifecycleCoversAllMainStates(): void
    {
        $contact = new Contact('user@example.com', 'Ada', 'Lovelace', '1 Main Street', new \DateTimeImmutable('1990-01-01'), '+33102030405');
        $tenant = new Tenant('acme', 'Acme', 'admin@example.com', 'Ada', 'Lovelace');
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));

        self::assertSame(DemoRequest::STATUS_REQUESTED, $demoRequest->getStatus());

        $demoRequest->setOnboardingTokenHash('token-hash');
        self::assertSame(DemoRequest::STATUS_ONBOARDING_SENT, $demoRequest->getStatus());
        self::assertSame('token-hash', $demoRequest->getOnboardingTokenHash());

        $demoRequest->markExpired();
        self::assertSame(DemoRequest::STATUS_EXPIRED, $demoRequest->getStatus());

        $demoRequest->cancel();
        self::assertSame(DemoRequest::STATUS_CANCELLED, $demoRequest->getStatus());

        $demoRequest->setOnboardingTokenHash('token-hash-2');
        $demoRequest->markAccepted();
        self::assertSame(DemoRequest::STATUS_ACCEPTED, $demoRequest->getStatus());
        self::assertNotNull($demoRequest->getAcceptedAt());
        self::assertNull($demoRequest->getOnboardingTokenHash());
    }

    public function testContactConstructorNormalizesInput(): void
    {
        $birthDate = new \DateTimeImmutable('1989-05-04');
        $contact = new Contact('  USER@Example.COM ', ' Ada ', ' Lovelace ', ' 1 Main Street ', $birthDate, ' +33102030405 ');

        self::assertSame('user@example.com', $contact->getEmail());
        self::assertSame('Ada', $contact->getFirstName());
        self::assertSame('Lovelace', $contact->getLastName());
        self::assertSame('1 Main Street', $contact->getAddress());
        self::assertSame($birthDate, $contact->getBirthDate());
        self::assertSame('+33102030405', $contact->getPhone());
    }

    public function testAuditEventSettersAndGetters(): void
    {
        $tenant = new Tenant('acme', 'Acme', 'owner@example.com', 'Ada', 'Lovelace');
        $event = new AuditEvent($tenant, ' update ', ' tenant ');
        $userId = Uuid::v7();

        $event
            ->setUserId($userId)
            ->setStatus(' ok ')
            ->setBeforeData(['status' => 'requested'])
            ->setAfterData(['status' => 'created'])
            ->setIpAddress('127.0.0.1')
            ->setUserAgent('phpunit-agent')
            ->setCorrelationId('corr-id-1');

        self::assertSame($tenant, $event->getTenant());
        self::assertSame($userId, $event->getUserId());
        self::assertSame('update', $event->getAction());
        self::assertSame('tenant', $event->getResource());
        self::assertSame('ok', $event->getStatus());
        self::assertSame(['status' => 'requested'], $event->getBeforeData());
        self::assertSame(['status' => 'created'], $event->getAfterData());
        self::assertSame('127.0.0.1', $event->getIpAddress());
        self::assertSame('phpunit-agent', $event->getUserAgent());
        self::assertSame('corr-id-1', $event->getCorrelationId());
    }

    public function testTenantMigrationVersionTrimsVersionString(): void
    {
        $tenant = new Tenant('acme', 'Acme', 'owner@example.com', 'Ada', 'Lovelace');
        $migration = new TenantMigrationVersion($tenant, ' 20260303123000 ');

        self::assertSame($tenant, $migration->getTenant());
        self::assertSame('20260303123000', $migration->getVersion());
        self::assertInstanceOf(\DateTimeImmutable::class, $migration->getAppliedAt());
    }
}
