<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditEvent;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Entity\Tenant;
use App\Entity\TenantMigrationVersion;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        #[Autowire('%env(string:NETDATA_PUBLIC_URL)%')] private readonly string $netdataUrl,
        #[Autowire('%env(string:UPTIME_KUMA_PUBLIC_URL)%')] private readonly string $uptimeKumaUrl,
    ) {
    }

    public function index(): Response
    {
        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(TenantCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SaaS Base Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Main Database');
        yield MenuItem::linkToCrud('Tenants', 'fa fa-building', Tenant::class);
        yield MenuItem::linkToCrud('Contacts', 'fa fa-address-book', Contact::class);
        yield MenuItem::linkToCrud('Demo Requests', 'fa fa-vial', DemoRequest::class);
        yield MenuItem::linkToCrud('Audit Events', 'fa fa-shield-halved', AuditEvent::class);
        yield MenuItem::linkToCrud('Tenant Migrations', 'fa fa-database', TenantMigrationVersion::class);

        yield MenuItem::section('Operations');
        yield MenuItem::linkToRoute('Monitoring & Logs', 'fa fa-gauge-high', 'admin_ops');

        if ('' !== $this->netdataUrl) {
            yield MenuItem::linkToUrl('Netdata', 'fa fa-chart-line', $this->netdataUrl)
                ->setLinkTarget('_blank');
        }

        if ('' !== $this->uptimeKumaUrl) {
            yield MenuItem::linkToUrl('Uptime Kuma', 'fa fa-heart-pulse', $this->uptimeKumaUrl)
                ->setLinkTarget('_blank');
        }
    }
}
