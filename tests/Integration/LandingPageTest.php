<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LandingPageTest extends WebTestCase
{
    public function testLandingPageIsAvailable(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('form[data-demo-form]')->count());
        self::assertStringContainsString('Demandez votre démo de coffre-fort', $client->getResponse()->getContent());
        self::assertSame('vault', $crawler->filter('input[name="child_app_key"]')->attr('value'));
    }

    public function testUnknownChildAppPageReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/ops');

        self::assertResponseStatusCodeSame(404);
    }
}
