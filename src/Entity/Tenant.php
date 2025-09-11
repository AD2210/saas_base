<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: "tenant")]
class Tenant {
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type: "bigint")] private ?int $id = null;
    #[ORM\Column(type: "string", length: 80, unique: true)] private string $slug;
    #[ORM\Column(type: "string", length: 160)] private string $name;
    #[ORM\Column(type: "string", length: 32)] private string $plan = 'starter';
    #[ORM\Column(type: "boolean")] private bool $isActive = false;
    #[ORM\Column(type: "string", length: 120)] private string $dbHost;
    #[ORM\Column(type: "string", length: 120)] private string $dbName;
    #[ORM\Column(type: "text")] private string $encDbUser;
    #[ORM\Column(type: "text")] private string $encDbPass;
    #[ORM\Column(type: "datetime_immutable")] private \DateTimeImmutable $createdAt;
    public function __construct(string $slug, string $name) { $this->slug=$slug; $this->name=$name; $this->createdAt=new \DateTimeImmutable();}
    public function getId(): ?int {return $this->id;} public function getSlug(): string {return $this->slug;} public function setSlug(string $slug): self {$this->slug=$slug; return $this;}
    public function getName(): string {return $this->name;} public function setName(string $name): self {$this->name=$name; return $this;}
    public function getPlan(): string {return $this->plan;} public function setPlan(string $plan): self {$this->plan=$plan; return $this;}
    public function isActive(): bool {return $this->isActive;} public function activate(): self {$this->isActive=true; return $this;} public function deactivate(): self {$this->isActive=false; return $this;}
    public function getDbHost(): string {return $this->dbHost;} public function setDbHost(string $dbHost): self {$this->dbHost=$dbHost; return $this;}
    public function getDbName(): string {return $this->dbName;} public function setDbName(string $dbName): self {$this->dbName=$dbName; return $this;}
    public function getEncDbUser(): string {return $this->encDbUser;} public function setEncDbUser(string $encDbUser): self {$this->encDbUser=$encDbUser; return $this;}
    public function getEncDbPass(): string {return $this->encDbPass;} public function setEncDbPass(string $encDbPass): self {$this->encDbPass=$encDbPass; return $this;}
    public function getCreatedAt(): \DateTimeImmutable {return $this->createdAt;}
}