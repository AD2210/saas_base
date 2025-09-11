<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class TenantResolverTest extends TestCase
{
    public function test_resolve_by_header_placeholder(): void
    {
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_X_TENANT' => 'acme']);
        $this->assertSame('acme', $req->headers->get('x-tenant'));
    }
}
