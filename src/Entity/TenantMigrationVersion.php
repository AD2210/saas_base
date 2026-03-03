<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_migration_version')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_version', columns: ['tenant_id', 'version'])]
#[ORM\HasLifecycleCallbacks]
class TenantMigrationVersion
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $version;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $appliedAt;

    public function __construct(Tenant $tenant, string $version)
    {
        $this->tenant = $tenant;
        $this->version = trim($version);
        $this->appliedAt = new \DateTimeImmutable();
    }

    public function getAppliedAt(): \DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
