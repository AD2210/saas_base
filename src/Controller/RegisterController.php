<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Demo\DemoRequestManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final readonly class RegisterController
{
    public function __construct(
        private DemoRequestManager $demoRequestManager,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/demo-requests', name: 'app_demo_request', methods: ['POST'])]
    #[Route('/register', name: 'app_register_legacy', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->extractInput($request);
        $errors = $this->validate($payload);
        if ([] !== $errors) {
            return new JsonResponse(['status' => 'invalid', 'errors' => $errors], 422);
        }

        $birthDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $payload['birth_date']);
        if (!$birthDate instanceof \DateTimeImmutable) {
            return new JsonResponse(['status' => 'invalid', 'errors' => ['birth_date must use YYYY-MM-DD']], 422);
        }

        try {
            $demoRequest = $this->demoRequestManager->requestDemo(
                email: (string) $payload['email'],
                firstName: (string) $payload['first_name'],
                lastName: (string) $payload['last_name'],
                address: (string) $payload['address'],
                birthDate: $birthDate,
                phone: (string) $payload['phone'],
                company: (string) $payload['company'],
                baseUrl: $request->getSchemeAndHttpHost(),
                slug: isset($payload['slug']) ? (string) $payload['slug'] : null,
            );
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

        $tenant = $demoRequest->getTenant();

        return new JsonResponse([
            'status' => 'requested',
            'demo_request_uuid' => $demoRequest->getIdString(),
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'demo_expires_at' => $demoRequest->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ], 201);
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

        return $errors;
    }
}
