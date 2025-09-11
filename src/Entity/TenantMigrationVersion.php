<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "tenant_migration_version")]
#[ORM\UniqueConstraint(name: "uniq_tenant_version", columns: ["tenant_id", "version"])]
class TenantMigrationVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: "tenant_id", referencedColumnName: "id", onDelete: "CASCADE", nullable: false)]
    private Tenant $tenant;
    #[ORM\Column(type: "string", length: 64)]
    private string $version;
    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $appliedAt;

    public function __construct(Tenant $tenant, string $version)
    {
        $this->tenant = $tenant;
        $this->version = $version;
        $this->appliedAt = new \DateTimeImmutable();
    }
}
