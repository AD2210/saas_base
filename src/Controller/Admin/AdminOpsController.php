<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminOpsController extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernel,
        #[Autowire('%env(string:NETDATA_PUBLIC_URL)%')] private readonly string $netdataUrl,
        #[Autowire('%env(string:UPTIME_KUMA_PUBLIC_URL)%')] private readonly string $uptimeKumaUrl,
    ) {
    }

    #[Route('/admin/ops', name: 'admin_ops', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function __invoke(): Response
    {
        $logFile = sprintf('%s/%s.log', $this->kernel->getLogDir(), $this->kernel->getEnvironment());

        return $this->render('admin/ops.html.twig', [
            'netdata_url' => $this->netdataUrl,
            'uptime_kuma_url' => $this->uptimeKumaUrl,
            'log_file' => $logFile,
            'log_excerpt' => $this->tailFile($logFile, 120),
        ]);
    }

    /**
     * @return list<string>
     */
    private function tailFile(string $path, int $lineCount): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (false === $lines) {
            return [];
        }

        return array_slice($lines, -1 * $lineCount);
    }
}
