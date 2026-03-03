<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProvisioningTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertSame('OK', $client->getResponse()->getContent());
    }

    public function testReadyEndpointReturnsReady(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('READY', $client->getResponse()->getContent());
    }
}
