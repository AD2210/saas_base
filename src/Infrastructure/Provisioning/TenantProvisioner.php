<?php
namespace App\Infrastructure\Provisioning;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
final class TenantProvisioner {
    public function __construct(private readonly EntityManagerInterface $em, private readonly DbOrchestrator $db, private readonly SecretBox $crypto, private readonly LoggerInterface $logger) {}
    public function createTenantAccount(string $email, string $company, ?string $slug=null): Tenant {
        $slug = $slug ?: strtolower(preg_replace('/[^a-z0-9]+/', '-', $company));
        $tenant = new Tenant($slug, $company);
        $this->em->persist($tenant); $this->em->flush();
        $this->logger->info('tenant.account.created', ['tenant_slug'=>$slug,'email'=>$email]);
        return $tenant;
    }
    public function provisionDatabase(Tenant $tenant): void {
        $creds = $this->db->createDatabase($tenant->getSlug());
        $tenant->setDbHost($creds['host']); $tenant->setDbName($creds['dbname']);
        $tenant->setEncDbUser($this->crypto->encrypt($creds['user']));
        $tenant->setEncDbPass($this->crypto->encrypt($creds['password']));
        // TODO: run tenant migrations here
        $tenant->activate(); $this->em->flush();
        $this->logger->info('tenant.db.provisioned', ['tenant_slug'=>$tenant->getSlug()]);
    }
}