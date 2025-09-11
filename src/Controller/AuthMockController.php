<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class AuthMockController
{

    public function __construct(
        #[Autowire(service: 'limiter.login')] private RateLimiterFactory                 $loginLimiter,
        #[Autowire(service: 'monolog.logger.security')] private \Psr\Log\LoggerInterface $securityLogger,
        #[Autowire(service: 'monolog.logger.audit')] private \Psr\Log\LoggerInterface    $auditLogger,
    )
    {}

    #[Route('/login-mock', name: 'app_login_mock', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $username = (string)$request->request->get('username', 'demo');

        $limiter = $this->loginLimiter->create($ip . ':' . $username);
        $rate = $limiter->consume(1);

        if (!$rate->isAccepted()) {
            $retryAfter = $rate->getRetryAfter()->getTimestamp();
            $this->securityLogger->warning('login.throttled', ['ip' => $ip, 'username' => $username, 'retry_after' => $retryAfter]);
            return new JsonResponse(['status' => 'throttled', 'retry_after' => $retryAfter], 429);
        }

        $ok = ($request->request->get('password') === 'secret');
        if (!$ok) {
            $this->securityLogger->notice('login.failed', ['ip' => $ip, 'username' => $username]);
            return new JsonResponse(['status' => 'invalid'], 401);
        }

        $this->auditLogger->info('login.success', ['action' => 'login', 'resource' => 'auth', 'status' => 'ok']);
        return new JsonResponse(['status' => 'ok'], 200);
    }
}
