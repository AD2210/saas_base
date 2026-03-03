<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_event')]
#[ORM\HasLifecycleCallbacks]
class AuditEvent
{
    use UuidPrimaryKeyTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'occurred_at')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $resource;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $beforeData = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $afterData = null;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $status = 'ok';

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $correlationId = null;

    public function __construct(Tenant $tenant, string $action, string $resource)
    {
        $this->tenant = $tenant;
        $this->action = trim($action);
        $this->resource = trim($resource);
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    /** @return array<string, mixed>|null */
    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    /** @return array<string, mixed>|null */
    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function setUserId(?Uuid $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /** @param array<string, mixed>|null $beforeData */
    public function setBeforeData(?array $beforeData): self
    {
        $this->beforeData = $beforeData;

        return $this;
    }

    /** @param array<string, mixed>|null $afterData */
    public function setAfterData(?array $afterData): self
    {
        $this->afterData = $afterData;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = trim($status);

        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function setCorrelationId(?string $correlationId): self
    {
        $this->correlationId = $correlationId;

        return $this;
    }
}
