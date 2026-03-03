<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\SoftDeleteTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contact')]
#[ORM\UniqueConstraint(name: 'uniq_contact_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    use UuidPrimaryKeyTrait;
    use TimestampableTrait;
    use SoftDeleteTrait;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $lastName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $address;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $birthDate;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $phone;

    public function __construct(
        string $email,
        string $firstName,
        string $lastName,
        string $address,
        \DateTimeImmutable $birthDate,
        string $phone,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->address = trim($address);
        $this->birthDate = $birthDate;
        $this->phone = trim($phone);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getBirthDate(): \DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }
}
