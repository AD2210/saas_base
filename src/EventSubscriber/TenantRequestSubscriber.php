<?php
namespace App\EventSubscriber;
use App\Domain\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
class TenantRequestSubscriber implements EventSubscriberInterface {
    public function __construct(private TenantContext $context) {}
    public static function getSubscribedEvents(): array { return [ KernelEvents::REQUEST => ['onKernelRequest', 102] ]; }
    public function onKernelRequest(RequestEvent $event): void {
        $req = $event->getRequest();
        $slug = $req->headers->get('x-tenant') ?: null;
        $this->context->set(null, $slug);
    }
}