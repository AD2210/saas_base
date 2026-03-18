<?php

declare(strict_types=1);

namespace App\Controller;

use App\ChildApp\ChildAppCatalog;
use App\ChildApp\ChildAppProfile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

final class LandingController extends AbstractController
{
    public function __construct(private readonly ChildAppCatalog $childAppCatalog)
    {
    }

    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function index(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderLanding($this->childAppCatalog->getDefault());
    }

    #[Route('/demo/{childAppKey}', name: 'app_landing_child_app', methods: ['GET'])]
    public function childApp(string $childAppKey): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->childAppCatalog->hasKey($childAppKey)) {
            throw $this->createNotFoundException(sprintf('Unknown child app "%s".', $childAppKey));
        }

        return $this->renderLanding($this->childAppCatalog->getByKey($childAppKey));
    }

    private function renderLanding(ChildAppProfile $childApp): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('landing/index.html.twig', [
            'child_app_profile' => $childApp,
            'child_app_profiles' => $this->childAppCatalog->all(),
            'default_child_app_key' => $this->childAppCatalog->getDefault()->getKey(),
            'app_theme_style' => $childApp->getThemeStyle(),
        ]);
    }
}
