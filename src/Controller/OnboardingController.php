<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Demo\PasswordPolicy;
use App\Entity\DemoRequest;
use App\Infrastructure\Provisioning\ChildAppAdminClient;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly OnboardingTokenManager $tokenManager,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly ChildAppAdminClient $childAppAdminClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/onboarding/set-password', name: 'app_onboarding_set_password', methods: ['GET', 'POST'])]
    public function setPassword(Request $request): Response
    {
        $token = 'POST' === $request->getMethod()
            ? (string) $request->request->get('token', '')
            : (string) $request->query->get('token', '');

        $context = $this->resolveOnboardingContext($token);
        $context['password_errors'] = [];

        if ('POST' === $request->getMethod() && 'valid' === $context['state']) {
            $context = $this->handlePasswordSubmission($request, $context);
        }

        return $this->render('onboarding/set_password.html.twig', $context);
    }

    /**
     * @param array{
     *     state: string,
     *     token: string,
     *     message: string,
     *     demo_request: DemoRequest|null,
     *     payload: array{tenant_uuid: string, user_uuid: string, email: string, exp: int}|null,
     *     password_errors: list<string>
     * } $context
     *
     * @return array{
     *     state: string,
     *     token: string,
     *     message: string,
     *     demo_request: DemoRequest|null,
     *     payload: array{tenant_uuid: string, user_uuid: string, email: string, exp: int}|null,
     *     password_errors: list<string>
     * }
     */
    private function handlePasswordSubmission(Request $request, array $context): array
    {
        $password = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');

        $errors = $this->passwordPolicy->validate($password);
        if ($password !== $passwordConfirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ([] !== $errors) {
            $context['state'] = 'invalid_password';
            $context['message'] = 'Please fix password requirements and retry.';
            $context['password_errors'] = $errors;

            return $context;
        }

        $demoRequest = $context['demo_request'];
        if (!$demoRequest instanceof DemoRequest) {
            $context['state'] = 'invalid';
            $context['message'] = 'Onboarding context is no longer valid.';

            return $context;
        }

        try {
            $this->childAppAdminClient->syncTenantAdmin($demoRequest->getTenant(), $password);
        } catch (\Throwable $exception) {
            $this->logger->error('onboarding.password.sync_failed', [
                'demo_request_uuid' => $demoRequest->getIdString(),
                'tenant_uuid' => $demoRequest->getTenant()->getIdString(),
                'error' => $exception->getMessage(),
            ]);

            $context['state'] = 'sync_failed';
            $context['message'] = 'Password was validated but tenant activation failed. Please retry later.';

            return $context;
        }

        $demoRequest->markAccepted();
        $this->em->flush();

        $this->logger->info('onboarding.password.accepted', [
            'demo_request_uuid' => $demoRequest->getIdString(),
            'tenant_uuid' => $demoRequest->getTenant()->getIdString(),
        ]);

        $context['state'] = 'accepted';
        $context['message'] = 'Password saved. You can now continue in your tenant application.';
        $context['password_errors'] = [];
        $context['demo_request'] = $demoRequest;

        return $context;
    }

    /**
     * @return array{
     *     state: string,
     *     token: string,
     *     message: string,
     *     demo_request: DemoRequest|null,
     *     payload: array{tenant_uuid: string, user_uuid: string, email: string, exp: int}|null
     * }
     */
    private function resolveOnboardingContext(string $token): array
    {
        if ('' === $token) {
            return [
                'state' => 'invalid',
                'token' => '',
                'message' => 'Missing onboarding token.',
                'demo_request' => null,
                'payload' => null,
            ];
        }

        try {
            $payload = $this->tokenManager->parseToken($token);
        } catch (\Throwable) {
            return [
                'state' => 'invalid',
                'token' => $token,
                'message' => 'Invalid onboarding token.',
                'demo_request' => null,
                'payload' => null,
            ];
        }

        $demoRequest = $this->em->getRepository(DemoRequest::class)->findOneBy([
            'onboardingTokenHash' => hash('sha256', $token),
        ]);

        if (!$demoRequest instanceof DemoRequest) {
            return [
                'state' => 'invalid',
                'token' => $token,
                'message' => 'This onboarding link is unknown or already consumed.',
                'demo_request' => null,
                'payload' => $payload,
            ];
        }

        if ($this->tokenManager->isExpired($payload)) {
            if (DemoRequest::STATUS_EXPIRED !== $demoRequest->getStatus()) {
                $demoRequest->markExpired();
                $demoRequest->clearOnboardingToken();
                $this->em->flush();
            }

            return [
                'state' => 'expired',
                'token' => $token,
                'message' => 'This onboarding link has expired. Please request a new demo link.',
                'demo_request' => $demoRequest,
                'payload' => $payload,
            ];
        }

        if (DemoRequest::STATUS_ACCEPTED === $demoRequest->getStatus()) {
            return [
                'state' => 'accepted',
                'token' => $token,
                'message' => 'Onboarding was already completed for this link.',
                'demo_request' => $demoRequest,
                'payload' => $payload,
            ];
        }

        return [
            'state' => 'valid',
            'token' => $token,
            'message' => 'Create your password to activate access.',
            'demo_request' => $demoRequest,
            'payload' => $payload,
        ];
    }
}
