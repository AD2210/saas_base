<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HealthController
{
    #[Route('/healthz', name: 'app_healthz', methods: ['GET'])]
    public function healthz(): Response
    {
        return new Response('OK', Response::HTTP_OK);
    }

    #[Route('/ready', name: 'app_ready', methods: ['GET'])]
    public function ready(): Response
    {
        return new Response('READY', Response::HTTP_OK);
    }
}
