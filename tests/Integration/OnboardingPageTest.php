<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OnboardingPageTest extends WebTestCase
{
    public function testOnboardingPageDisplaysErrorWithoutToken(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/onboarding/set-password');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Missing onboarding token.', $client->getResponse()->getContent());
        self::assertSame(1, $crawler->filter('.onboarding-alert--error')->count());
    }

    public function testOnboardingPageDisplaysErrorWithInvalidToken(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/onboarding/set-password?token=invalid');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invalid onboarding token.', $client->getResponse()->getContent());
        self::assertSame(1, $crawler->filter('.onboarding-alert--error')->count());
    }
}
