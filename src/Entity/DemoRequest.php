<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\SoftDeleteTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demo_request')]
#[ORM\HasLifecycleCallbacks]
class DemoRequest
{
    use UuidPrimaryKeyTrait;
    use TimestampableTrait;
    use SoftDeleteTrait;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ONBOARDING_SENT = 'onboarding_sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: 'contact_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $status = self::STATUS_REQUESTED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $onboardingTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function __construct(Contact $contact, Tenant $tenant, \DateTimeImmutable $expiresAt)
    {
        $this->contact = $contact;
        $this->tenant = $tenant;
        $this->requestedAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getChildAppKey(): string
    {
        return $this->tenant->getChildAppKey();
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getOnboardingTokenHash(): ?string
    {
        return $this->onboardingTokenHash;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setOnboardingTokenHash(string $hash): void
    {
        $this->onboardingTokenHash = $hash;
        $this->status = self::STATUS_ONBOARDING_SENT;
    }

    public function markAccepted(): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->acceptedAt = new \DateTimeImmutable();
        $this->onboardingTokenHash = null;
    }

    public function markExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
    }

    public function clearOnboardingToken(): void
    {
        $this->onboardingTokenHash = null;
    }
}
