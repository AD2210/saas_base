<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ChildApp\ChildAppCatalog;
use App\Controller\OnboardingController;
use App\Domain\Demo\PasswordPolicy;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Entity\Tenant;
use App\Infrastructure\Provisioning\ChildAppAdminClient;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use App\Infrastructure\Provisioning\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class OnboardingControllerTest extends TestCase
{
    private const SECRET_BOX_KEY = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';

    public function testGetWithoutTokenReturnsInvalidContext(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');

        [$controller] = $this->buildController($em, $this->childClientSuccess());
        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'GET'));

        $payload = $this->decodeResponse($response);
        self::assertSame('invalid', $payload['state']);
        self::assertSame('Missing onboarding token.', $payload['message']);
    }

    public function testGetWithUnknownTokenReturnsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $clock = new MockClock('2026-03-03 12:00:00');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->with(DemoRequest::class)->willReturn($repo);
        $em->expects($this->never())->method('flush');

        [$controller, $tokenManager] = $this->buildController($em, $this->childClientSuccess(), $clock);
        $token = $tokenManager->generateToken($tenant, 3600);

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'GET', ['token' => $token]));

        $payload = $this->decodeResponse($response);
        self::assertSame('invalid', $payload['state']);
        self::assertSame('This onboarding link is unknown or already consumed.', $payload['message']);
    }

    public function testGetWithExpiredTokenMarksRequestAsExpired(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));

        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 1),
            $this->childClientSuccess(),
            $clock
        );
        $token = $tokenManager->generateToken($tenant, -10);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'GET', ['token' => $token]));

        $payload = $this->decodeResponse($response);
        self::assertSame('expired', $payload['state']);
        self::assertSame(DemoRequest::STATUS_EXPIRED, $demoRequest->getStatus());
        self::assertNull($demoRequest->getOnboardingTokenHash());
    }

    public function testPostWithInvalidPasswordReturnsExplicitValidationErrors(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));
        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 0),
            $this->childClientFailure(),
            $clock
        );
        $token = $tokenManager->generateToken($tenant, 3600);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'POST', [
            'token' => $token,
            'password' => 'weak',
            'password_confirm' => 'different',
        ]));

        $payload = $this->decodeResponse($response);
        self::assertSame('invalid_password', $payload['state']);
        self::assertContains('Password must contain at least 12 characters.', $payload['password_errors']);
        self::assertContains('Password confirmation does not match.', $payload['password_errors']);
        self::assertSame(DemoRequest::STATUS_ONBOARDING_SENT, $demoRequest->getStatus());
    }

    public function testGetWithAlreadyAcceptedRequestReturnsAcceptedState(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));
        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 0),
            $this->childClientSuccess(),
            $clock
        );
        $token = $tokenManager->generateToken($tenant, 3600);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));
        $this->forceDemoRequestStatus($demoRequest, DemoRequest::STATUS_ACCEPTED);

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'GET', ['token' => $token]));

        $payload = $this->decodeResponse($response);
        self::assertSame('accepted', $payload['state']);
        self::assertSame('Onboarding was already completed for this link.', $payload['message']);
    }

    public function testPostWithValidPasswordMarksOnboardingAsAccepted(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));
        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 1),
            $this->childClientSuccess(),
            $clock,
            $this->childAppCatalog('https://child-app.local', 'https://{tenantSlug}.vault.example.com/login', 'token')
        );
        $token = $tokenManager->generateToken($tenant, 3600);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'POST', [
            'token' => $token,
            'password' => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ]));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://acme.vault.example.com/login?email=admin%40example.com', $response->headers->get('Location'));
        self::assertSame(DemoRequest::STATUS_ACCEPTED, $demoRequest->getStatus());
        self::assertNull($demoRequest->getOnboardingTokenHash());
        self::assertNotNull($demoRequest->getAcceptedAt());
    }

    public function testPostWithValidPasswordRendersAcceptedStateWhenChildLoginUrlIsMissing(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));
        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 1),
            $this->childClientSuccess(),
            $clock,
            $this->childAppCatalog('https://child-app.local', '', 'token')
        );
        $token = $tokenManager->generateToken($tenant, 3600);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'POST', [
            'token' => $token,
            'password' => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ]));

        $payload = $this->decodeResponse($response);
        self::assertSame('accepted', $payload['state']);
        self::assertNull($payload['child_app_login_url']);
        self::assertSame(DemoRequest::STATUS_ACCEPTED, $demoRequest->getStatus());
    }

    public function testPostWithChildAppSyncFailureReturnsExplicitState(): void
    {
        $tenant = $this->createTenant();
        $contact = $this->createContact();
        $demoRequest = new DemoRequest($contact, $tenant, new \DateTimeImmutable('+30 days'));
        $clock = new MockClock('2026-03-03 12:00:00');

        [$controller, $tokenManager] = $this->buildController(
            $this->entityManagerReturningDemoRequest($demoRequest, 0),
            $this->childClientFailure(),
            $clock
        );
        $token = $tokenManager->generateToken($tenant, 3600);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));

        $response = $controller->setPassword(Request::create('/onboarding/set-password', 'POST', [
            'token' => $token,
            'password' => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ]));

        $payload = $this->decodeResponse($response);
        self::assertSame('sync_failed', $payload['state']);
        self::assertSame(DemoRequest::STATUS_ONBOARDING_SENT, $demoRequest->getStatus());
    }

    /**
     * @return array{OnboardingController, OnboardingTokenManager}
     */
    private function buildController(
        EntityManagerInterface $em,
        ChildAppAdminClient $childAppAdminClient,
        ?MockClock $clock = null,
        ?ChildAppCatalog $childAppCatalog = null,
    ): array {
        $tokenManager = new OnboardingTokenManager(new SecretBox(self::SECRET_BOX_KEY), $clock ?? new MockClock('2026-03-03 12:00:00'));
        $controller = new OnboardingController(
            $tokenManager,
            new PasswordPolicy(),
            $childAppAdminClient,
            $em,
            new NullLogger(),
            $childAppCatalog ?? $this->childAppCatalog(),
        );
        $controller->setContainer($this->twigContainer());

        return [$controller, $tokenManager];
    }

    private function entityManagerReturningDemoRequest(DemoRequest $demoRequest, int $flushCount): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findOneBy')->willReturn($demoRequest);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getRepository')->with(DemoRequest::class)->willReturn($repo);
        if ($flushCount > 0) {
            $em->expects($this->exactly($flushCount))->method('flush');
        } else {
            $em->expects($this->never())->method('flush');
        }

        return $em;
    }

    private function childClientSuccess(): ChildAppAdminClient
    {
        return new ChildAppAdminClient(new MockHttpClient(), new NullLogger(), $this->childAppCatalog('', 'https://vault.example/login', ''), 'dev');
    }

    private function childClientFailure(): ChildAppAdminClient
    {
        return new ChildAppAdminClient(new MockHttpClient(), new NullLogger(), $this->childAppCatalog('https://child-app.local', 'https://vault.example/login', ''), 'dev');
    }

    private function createTenant(): Tenant
    {
        return new Tenant('acme', 'Acme', 'admin@example.com', 'Ada', 'Lovelace');
    }

    private function createContact(): Contact
    {
        return new Contact('owner@example.com', 'Owner', 'Person', '1 Demo Street', new \DateTimeImmutable('1990-01-01'), '+33102030405');
    }

    private function forceDemoRequestStatus(DemoRequest $demoRequest, string $status): void
    {
        $statusProperty = new \ReflectionProperty($demoRequest, 'status');
        $statusProperty->setValue($demoRequest, $status);
    }

    /**
     * @return array{state: string, message: string, password_errors: list<string>, child_app_login_url: string|null}
     */
    private function decodeResponse(Response $response): array
    {
        /** @var array{state: string, message: string, password_errors: list<string>, child_app_login_url: string|null} $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function childAppCatalog(
        string $apiUrl = 'https://child-app.local',
        string $loginUrl = 'https://vault.example/login',
        string $apiToken = 'token',
    ): ChildAppCatalog {
        return new ChildAppCatalog([
            'default_key' => 'vault',
            'apps' => [
                'vault' => [
                    'name' => 'Client Secrets Vault',
                    'api_url' => $apiUrl,
                    'login_url' => $loginUrl,
                    'api_token' => $apiToken,
                ],
            ],
        ]);
    }

    private function twigContainer(): ContainerInterface
    {
        $twig = new class {
            /**
             * @param array<string, mixed> $parameters
             */
            public function render(string $view, array $parameters = []): string
            {
                return (string) json_encode([
                    'view' => $view,
                    'state' => (string) ($parameters['state'] ?? ''),
                    'message' => (string) ($parameters['message'] ?? ''),
                    'password_errors' => isset($parameters['password_errors']) && is_array($parameters['password_errors'])
                        ? $parameters['password_errors']
                        : [],
                    'child_app_login_url' => array_key_exists('child_app_login_url', $parameters) && (is_string($parameters['child_app_login_url']) || null === $parameters['child_app_login_url'])
                        ? $parameters['child_app_login_url']
                        : null,
                ], JSON_THROW_ON_ERROR);
            }
        };

        $container = new Container();
        $container->set('twig', $twig);

        return $container;
    }
}
