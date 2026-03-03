<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait UuidPrimaryKeyTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\PrePersist]
    public function initializeUuid(): void
    {
        if (!isset($this->id)) {
            $this->id = Uuid::v7();
        }
    }

    public function getId(): Uuid
    {
        if (!isset($this->id)) {
            $this->id = Uuid::v7();
        }

        return $this->id;
    }

    public function getIdString(): string
    {
        return $this->getId()->toRfc4122();
    }
}
