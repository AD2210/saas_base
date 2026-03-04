<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Tenancy\TenantContext;
use App\EventSubscriber\TenantRequestSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class TenantRequestSubscriberTest extends TestCase
{
    public function testSubscribedEventsExposeKernelRequestHook(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 102]], TenantRequestSubscriber::getSubscribedEvents());
    }

    public function testOnKernelRequestStoresTenantSlugFromHeader(): void
    {
        $context = new TenantContext();
        $subscriber = new TenantRequestSubscriber($context);

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_TENANT' => 'acme']);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($context->id());
        self::assertSame('acme', $context->slug());
    }

    public function testOnKernelRequestStoresNullSlugWhenHeaderMissing(): void
    {
        $context = new TenantContext();
        $subscriber = new TenantRequestSubscriber($context);

        $request = Request::create('/', 'GET');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($context->id());
        self::assertNull($context->slug());
    }
}
