<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\SoftDeleteTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tenant')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_tenant_admin_email', columns: ['admin_email'])]
#[ORM\HasLifecycleCallbacks]
class Tenant
{
    use UuidPrimaryKeyTrait;
    use TimestampableTrait;
    use SoftDeleteTrait;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_CREATED = 'created';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Column(type: 'string', length: 80)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'string', length: 32)]
    private string $plan = 'starter';

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = false;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_REQUESTED;

    #[ORM\Column(type: 'uuid', name: 'admin_user_uuid')]
    private Uuid $adminUserUuid;

    #[ORM\Column(type: 'string', length: 180, name: 'admin_email')]
    private string $adminEmail;

    #[ORM\Column(type: 'string', length: 120, name: 'admin_first_name')]
    private string $adminFirstName;

    #[ORM\Column(type: 'string', length: 120, name: 'admin_last_name')]
    private string $adminLastName;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $dbHost = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $dbName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encDbUser = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encDbPass = null;

    public function __construct(
        string $slug,
        string $name,
        string $adminEmail,
        string $adminFirstName,
        string $adminLastName,
        ?Uuid $adminUserUuid = null,
    ) {
        $this->slug = self::sanitizeSlug($slug);
        $this->name = trim($name);
        $this->adminEmail = mb_strtolower(trim($adminEmail));
        $this->adminFirstName = trim($adminFirstName);
        $this->adminLastName = trim($adminLastName);
        $this->adminUserUuid = $adminUserUuid ?? Uuid::v7();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = self::sanitizeSlug($slug);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): self
    {
        $this->plan = trim($plan);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAdminUserUuid(): Uuid
    {
        return $this->adminUserUuid;
    }

    public function getAdminUserUuidString(): string
    {
        return $this->adminUserUuid->toRfc4122();
    }

    public function getAdminEmail(): string
    {
        return $this->adminEmail;
    }

    public function getAdminFirstName(): string
    {
        return $this->adminFirstName;
    }

    public function getAdminLastName(): string
    {
        return $this->adminLastName;
    }

    public function getDbHost(): ?string
    {
        return $this->dbHost;
    }

    public function setDbHost(string $dbHost): self
    {
        $this->dbHost = trim($dbHost);

        return $this;
    }

    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): self
    {
        $this->dbName = trim($dbName);

        return $this;
    }

    public function getEncDbUser(): ?string
    {
        return $this->encDbUser;
    }

    public function setEncDbUser(string $encDbUser): self
    {
        $this->encDbUser = $encDbUser;

        return $this;
    }

    public function getEncDbPass(): ?string
    {
        return $this->encDbPass;
    }

    public function setEncDbPass(string $encDbPass): self
    {
        $this->encDbPass = $encDbPass;

        return $this;
    }

    public function markProvisioningCreated(): void
    {
        $this->status = self::STATUS_CREATED;
        $this->isActive = true;
    }

    public function markProvisioningFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->isActive = false;
    }

    public function hasProvisionedDatabase(): bool
    {
        return null !== $this->dbHost
            && null !== $this->dbName
            && null !== $this->encDbUser
            && null !== $this->encDbPass;
    }

    public function resetProvisioningCredentials(): void
    {
        $this->dbHost = null;
        $this->dbName = null;
        $this->encDbUser = null;
        $this->encDbPass = null;
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->isActive = false;
    }

    private static function sanitizeSlug(string $slug): string
    {
        $normalized = mb_strtolower(trim($slug));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        return '' === $normalized ? 'tenant' : $normalized;
    }
}
