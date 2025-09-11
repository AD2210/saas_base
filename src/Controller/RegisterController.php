<?php
namespace App\Controller;
use App\Infrastructure\Provisioning\TenantProvisioner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
final class RegisterController {
    public function __construct(private TenantProvisioner $prov) {}
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse {
        $email = $request->request->get('email', 'owner@example.tld');
        $company = $request->request->get('company', 'Acme');
        $slug = $request->request->get('slug');
        $tenant = $this->prov->createTenantAccount($email, $company, $slug);
        $this->prov->provisionDatabase($tenant);
        return new JsonResponse(['status'=>'ok','tenant'=>$tenant->getSlug()], 201);
    }
}