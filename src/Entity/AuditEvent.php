<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:"audit_event")]
class AuditEvent {
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type:"bigint")] private ?int $id=null;
    #[ORM\Column(type:"datetime_immutable")] private \DateTimeImmutable $at;
    #[ORM\ManyToOne(targetEntity: Tenant::class)] #[ORM\JoinColumn(name:"tenant_id", referencedColumnName:"id", onDelete:"CASCADE", nullable:false)] private Tenant $tenant;
    #[ORM\Column(type:"string", length:64, nullable:true)] private ?string $userId=null;
    #[ORM\Column(type:"string", length:64)] private string $action;
    #[ORM\Column(type:"string", length:128)] private string $resource;
    #[ORM\Column(type:"json", nullable:true)] private ?array $before=null;
    #[ORM\Column(type:"json", nullable:true)] private ?array $after=null;
    #[ORM\Column(type:"string", length:16)] private string $status='ok';
    #[ORM\Column(type:"string", length:64, nullable:true)] private ?string $ip=null;
    #[ORM\Column(type:"text", nullable:true)] private ?string $ua=null;
    #[ORM\Column(type:"string", length:64, nullable:true)] private ?string $corrId=null;
    public function __construct(Tenant $tenant, string $action, string $resource){$this->tenant=$tenant; $this->action=$action; $this->resource=$resource; $this->at=new \DateTimeImmutable();}
}