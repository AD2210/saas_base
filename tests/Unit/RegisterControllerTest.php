<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ChildApp\ChildAppCatalog;
use App\Controller\RegisterController;
use App\Domain\Demo\DemoRequestManager;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Entity\Tenant;
use App\Infrastructure\Provisioning\DbOrchestrator;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use App\Infrastructure\Provisioning\SecretBox;
use App\Infrastructure\Provisioning\TenantProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class RegisterControllerTest extends TestCase
{
    private const SECRET_BOX_KEY = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';

    public function testInvokeReturnsValidationErrorsWhenRequiredFieldsAreMissing(): void
    {
        $controller = new RegisterController($this->buildDemoRequestManager(false), $this->childAppCatalog(), new NullLogger(), $this->urlGenerator());

        $response = $controller($this->jsonRequest([]));
        $payload = $this->decodeJsonResponse($response->getContent() ?: '{}');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('invalid', $payload['status']);
        self::assertContains('email is required', $payload['errors']);
        self::assertContains('company is required', $payload['errors']);
    }

    public function testInvokeReturnsValidationErrorWhenBirthDateFormatIsInvalid(): void
    {
        $controller = new RegisterController($this->buildDemoRequestManager(false), $this->childAppCatalog(), new NullLogger(), $this->urlGenerator());

        $response = $controller($this->jsonRequest([
            'email' => 'owner@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address' => '1 Main Street',
            'birth_date' => '01-01-1990',
            'phone' => '+33102030405',
            'company' => 'Acme',
        ]));
        $payload = $this->decodeJsonResponse($response->getContent() ?: '{}');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['birth_date must use YYYY-MM-DD'], $payload['errors']);
    }

    public function testInvokeReturnsCreatedPayloadWhenProvisioningSucceeds(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $email): bool {
                return str_contains((string) $email->getTextBody(), '/onboarding/set-password?token=');
            }));

        $controller = new RegisterController($this->buildDemoRequestManager(false, $mailer), $this->childAppCatalog(), new NullLogger(), $this->urlGenerator());

        $response = $controller($this->jsonRequest([
            'email' => 'owner@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address' => '1 Main Street',
            'birth_date' => '1990-01-01',
            'phone' => '+33102030405',
            'company' => 'Acme Company',
        ]));
        $payload = $this->decodeJsonResponse($response->getContent() ?: '{}');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('requested', $payload['status']);
        self::assertTrue(Uuid::isValid((string) $payload['demo_request_uuid']));
        self::assertTrue(Uuid::isValid((string) $payload['tenant_uuid']));
        self::assertSame('acme-company', $payload['tenant_slug']);
        self::assertSame('vault', $payload['child_app_key']);
        self::assertSame('Client Secrets Vault', $payload['child_app_name']);
        self::assertNotSame(false, strtotime((string) $payload['demo_expires_at']));
    }

    public function testInvokeReturnsServiceUnavailableWhenProvisioningFails(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $controller = new RegisterController($this->buildDemoRequestManager(true, $mailer), $this->childAppCatalog(), new NullLogger(), $this->urlGenerator());

        $response = $controller($this->jsonRequest([
            'email' => 'owner@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address' => '1 Main Street',
            'birth_date' => '1990-01-01',
            'phone' => '+33102030405',
            'company' => 'Acme Company',
        ]));
        $payload = $this->decodeJsonResponse($response->getContent() ?: '{}');

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('failed', $payload['status']);
        self::assertSame('demo provisioning failed, please retry later', $payload['error']);
    }

    public function testInvokeRejectsUnknownChildAppKey(): void
    {
        $controller = new RegisterController($this->buildDemoRequestManager(false), $this->childAppCatalog(), new NullLogger(), $this->urlGenerator());

        $response = $controller($this->jsonRequest([
            'email' => 'owner@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address' => '1 Main Street',
            'birth_date' => '1990-01-01',
            'phone' => '+33102030405',
            'company' => 'Acme Company',
            'child_app_key' => 'unknown-app',
        ]));
        $payload = $this->decodeJsonResponse($response->getContent() ?: '{}');

        self::assertSame(422, $response->getStatusCode());
        self::assertContains('child_app_key is invalid', $payload['errors']);
    }

    private function buildDemoRequestManager(bool $provisioningFails, ?MailerInterface $mailer = null): DemoRequestManager
    {
        $contactRepository = $this->createMock(EntityRepository::class);
        $contactRepository->method('findOneBy')->willReturn(null);

        $tenantRepository = $this->createMock(EntityRepository::class);
        $tenantRepository->method('findOneBy')->willReturn(null);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $demoRequestRepository = $this->createMock(EntityRepository::class);
        $demoRequestRepository->method('createQueryBuilder')->with('demo_request')->willReturn($queryBuilder);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static function (string $className) use ($contactRepository, $tenantRepository, $demoRequestRepository): EntityRepository {
            if (Contact::class === $className) {
                return $contactRepository;
            }

            if (Tenant::class === $className) {
                return $tenantRepository;
            }

            if (DemoRequest::class === $className) {
                return $demoRequestRepository;
            }

            throw new \RuntimeException(sprintf('Unexpected repository "%s".', $className));
        });

        $dbOrchestrator = $this->createMock(DbOrchestrator::class);
        if ($provisioningFails) {
            $dbOrchestrator->method('createDatabase')->willThrowException(new \RuntimeException('db provisioning error'));
        } else {
            $dbOrchestrator->method('createDatabase')->willReturnCallback(static function (string $tenantId): array {
                return [
                    'host' => 'db',
                    'dbname' => 'db_'.str_replace('-', '_', $tenantId),
                    'user' => 'u_'.substr(str_replace('-', '_', $tenantId), 0, 24),
                    'password' => 'generated-password',
                ];
            });
        }
        $dbOrchestrator->expects($this->any())->method('rollbackDatabase');

        $provisioner = new TenantProvisioner($em, $dbOrchestrator, new SecretBox(self::SECRET_BOX_KEY), new NullLogger());
        $tokenManager = new OnboardingTokenManager(new SecretBox(self::SECRET_BOX_KEY), new MockClock('2026-03-03 12:00:00'));

        return new DemoRequestManager(
            $em,
            $provisioner,
            $tokenManager,
            $this->childAppCatalog(),
            $mailer ?? $this->createMock(MailerInterface::class),
            new NullLogger(),
            'noreply@example.com',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            'https://saas.local/api/demo-requests',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array{status: string, errors?: list<string>, error?: string, demo_request_uuid?: string, tenant_uuid?: string, tenant_slug?: string, child_app_key?: string, child_app_name?: string, demo_expires_at?: string}
     */
    private function decodeJsonResponse(string $content): array
    {
        /** @var array{status: string, errors?: list<string>, error?: string, demo_request_uuid?: string, tenant_uuid?: string, tenant_slug?: string, child_app_key?: string, child_app_name?: string, demo_expires_at?: string} $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function childAppCatalog(): ChildAppCatalog
    {
        return new ChildAppCatalog([
            'default_key' => 'vault',
            'apps' => [
                'vault' => [
                    'name' => 'Client Secrets Vault',
                    'api_url' => 'https://vault.example',
                    'login_url' => 'https://vault.example/login',
                    'api_token' => 'vault-token',
                ],
            ],
        ]);
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/api/demo-requests/resend-invitation');

        return $urlGenerator;
    }
}
