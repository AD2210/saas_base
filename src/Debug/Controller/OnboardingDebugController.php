<?php

declare(strict_types=1);

namespace App\Debug\Controller;

use App\Infrastructure\Provisioning\OnboardingTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final readonly class OnboardingDebugController
{
    public function __construct(private OnboardingTokenManager $tokenManager)
    {
    }

    #[Route('/debug/onboarding/validate', name: 'app_debug_onboarding_validate', methods: ['GET'])]
    public function validate(Request $request): JsonResponse
    {
        $token = (string) $request->query->get('token', '');
        if ('' === $token) {
            return new JsonResponse(['status' => 'invalid', 'error' => 'missing token'], 400);
        }

        try {
            $payload = $this->tokenManager->parseToken($token);

            return new JsonResponse([
                'status' => $this->tokenManager->isExpired($payload) ? 'expired' : 'valid',
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'invalid', 'error' => 'invalid token'], 400);
        }
    }
}
