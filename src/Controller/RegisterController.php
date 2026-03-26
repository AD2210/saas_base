<?php

declare(strict_types=1);

namespace App\Controller;

use App\ChildApp\ChildAppCatalog;
use App\ChildApp\ChildAppProfile;
use App\Domain\Demo\DemoRequestManager;
use App\Entity\DemoRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RegisterController
{
    public function __construct(
        private DemoRequestManager $demoRequestManager,
        private ChildAppCatalog $childAppCatalog,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/demo-requests', name: 'app_demo_request', methods: ['POST'])]
    #[Route('/register', name: 'app_register_legacy', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->extractInput($request);
        $childApp = null;
        $demoRequest = null;

        $errors = $this->validate($payload);
        if ([] !== $errors) {
            return new JsonResponse(['status' => 'invalid', 'errors' => $errors], 422);
        }

        $birthDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $payload['birth_date']);
        if (!$birthDate instanceof \DateTimeImmutable) {
            return new JsonResponse(['status' => 'invalid', 'errors' => ['birth_date must use YYYY-MM-DD']], 422);
        }

        try {
            $childApp = $this->childAppCatalog->resolve(isset($payload['child_app_key']) ? (string) $payload['child_app_key'] : null);
            $existingDemoRequest = $this->demoRequestManager->findLatestByEmailAndChildApp((string) $payload['email'], $childApp->getKey());
            if ($existingDemoRequest instanceof DemoRequest) {
                return $this->buildExistingRequestResponse($existingDemoRequest, $childApp);
            }

            $demoRequest = $this->demoRequestManager->requestDemo(
                email: (string) $payload['email'],
                firstName: (string) $payload['first_name'],
                lastName: (string) $payload['last_name'],
                address: (string) $payload['address'],
                birthDate: $birthDate,
                phone: (string) $payload['phone'],
                company: (string) $payload['company'],
                baseUrl: $request->getSchemeAndHttpHost(),
                childAppKey: $childApp->getKey(),
                slug: isset($payload['slug']) ? (string) $payload['slug'] : null,
            );
        } catch (UniqueConstraintViolationException) {
            $childApp = $this->childAppCatalog->resolve(isset($payload['child_app_key']) ? (string) $payload['child_app_key'] : null);
            $existingDemoRequest = $this->demoRequestManager->findLatestByEmailAndChildApp((string) $payload['email'], $childApp->getKey());
            if ($existingDemoRequest instanceof DemoRequest) {
                return $this->buildExistingRequestResponse($existingDemoRequest, $childApp);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('demo.request.failed', [
                'email' => (string) $payload['email'],
                'company' => (string) $payload['company'],
                'error' => $exception->getMessage(),
            ]);

            return new JsonResponse([
                'status' => 'failed',
                'error' => 'demo provisioning failed, please retry later',
            ], 503);
        }

        if (!$demoRequest instanceof DemoRequest) {
            return new JsonResponse([
                'status' => 'failed',
                'error' => 'demo provisioning failed, please retry later',
            ], 503);
        }

        $tenant = $demoRequest->getTenant();

        return new JsonResponse([
            'status' => 'requested',
            'demo_request_uuid' => $demoRequest->getIdString(),
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'child_app_key' => $tenant->getChildAppKey(),
            'child_app_name' => $childApp->getName(),
            'demo_expires_at' => $demoRequest->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/api/demo-requests/resend-invitation', name: 'app_demo_request_resend_invitation', methods: ['POST'])]
    public function resendInvitation(Request $request): JsonResponse
    {
        $payload = $this->extractInput($request);
        $email = trim((string) ($payload['email'] ?? ''));
        $childAppKey = isset($payload['child_app_key']) ? (string) $payload['child_app_key'] : null;
        $errors = [];

        if ('' === $email) {
            $errors[] = 'email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email is invalid';
        }

        if (null !== $childAppKey && '' !== trim($childAppKey) && !$this->childAppCatalog->hasKey(trim($childAppKey))) {
            $errors[] = 'child_app_key is invalid';
        }

        if ([] !== $errors) {
            return new JsonResponse(['status' => 'invalid', 'errors' => $errors], 422);
        }

        $childApp = $this->childAppCatalog->resolve($childAppKey);
        $existingDemoRequest = $this->demoRequestManager->findLatestByEmailAndChildApp($email, $childApp->getKey());
        if (!$existingDemoRequest instanceof DemoRequest) {
            return new JsonResponse([
                'status' => 'not_found',
                'message' => 'No existing invitation matches this email for the selected app.',
            ], 404);
        }

        if (DemoRequest::STATUS_ACCEPTED === $existingDemoRequest->getStatus()) {
            return $this->buildExistingRequestResponse($existingDemoRequest, $childApp);
        }

        $this->demoRequestManager->resendInvitation($existingDemoRequest, $request->getSchemeAndHttpHost());

        return new JsonResponse([
            'status' => 'invitation_resent',
            'message' => sprintf('A fresh onboarding email was sent to %s.', $existingDemoRequest->getContact()->getEmail()),
            'email' => $existingDemoRequest->getContact()->getEmail(),
            'tenant_slug' => $existingDemoRequest->getTenant()->getSlug(),
            'child_app_key' => $existingDemoRequest->getTenant()->getChildAppKey(),
            'child_app_name' => $childApp->getName(),
            'demo_request_uuid' => $existingDemoRequest->getIdString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInput(Request $request): array
    {
        $json = json_decode($request->getContent(), true);
        if (is_array($json)) {
            return $json;
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function validate(array $payload): array
    {
        $requiredFields = [
            'email',
            'first_name',
            'last_name',
            'address',
            'birth_date',
            'phone',
            'company',
        ];

        $errors = [];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $payload) || '' === trim((string) $payload[$field])) {
                $errors[] = sprintf('%s is required', $field);
            }
        }

        if (isset($payload['email']) && !filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email is invalid';
        }

        if (isset($payload['child_app_key']) && '' !== trim((string) $payload['child_app_key'])) {
            $childAppKey = trim((string) $payload['child_app_key']);
            if (!$this->childAppCatalog->hasKey($childAppKey)) {
                $errors[] = 'child_app_key is invalid';
            }
        }

        return $errors;
    }

    private function buildExistingRequestResponse(DemoRequest $demoRequest, ChildAppProfile $childApp): JsonResponse
    {
        $email = $demoRequest->getContact()->getEmail();
        $loginUrl = $childApp->getLoginUrl();
        if ('' !== $loginUrl) {
            $separator = str_contains($loginUrl, '?') ? '&' : '?';
            $loginUrl .= $separator.'email='.rawurlencode($email);
        }

        $isAccepted = DemoRequest::STATUS_ACCEPTED === $demoRequest->getStatus();

        return new JsonResponse([
            'status' => $isAccepted ? 'already_onboarded' : 'email_already_present',
            'message' => $isAccepted
                ? sprintf('This email already has access to %s.', $childApp->getName())
                : sprintf('An invitation already exists for %s in %s.', $email, $childApp->getName()),
            'email' => $email,
            'tenant_slug' => $demoRequest->getTenant()->getSlug(),
            'child_app_key' => $demoRequest->getTenant()->getChildAppKey(),
            'child_app_name' => $childApp->getName(),
            'demo_request_uuid' => $demoRequest->getIdString(),
            'request_status' => $demoRequest->getStatus(),
            'can_resend_invitation' => !$isAccepted,
            'resend_invitation_url' => $this->urlGenerator->generate('app_demo_request_resend_invitation'),
            'login_url' => '' !== $loginUrl ? $loginUrl : null,
        ], 409);
    }
}
