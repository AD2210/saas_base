<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicApiRoutesTest extends WebTestCase
{
    public function testDemoRequestRouteRejectsMissingPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/demo-requests', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        self::assertResponseStatusCodeSame(422);
        self::assertJson((string) $client->getResponse()->getContent());

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid', $payload['status'] ?? null);
    }

    public function testDemoRequestRouteRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/demo-requests', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'not-an-email',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => '1 Test street',
            'birth_date' => '1990-01-01',
            'phone' => '+33102030405',
            'company' => 'Acme',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid', $payload['status'] ?? null);
        self::assertContains('email is invalid', $payload['errors'] ?? []);
    }

    public function testLegacyRegisterRouteStillWorksForValidation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginMockRejectsInvalidPassword(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login-mock', [
            'username' => 'qa_user_invalid',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertJson((string) $client->getResponse()->getContent());
    }

    public function testLoginMockAcceptsExpectedPassword(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login-mock', [
            'username' => 'qa_user_valid',
            'password' => 'secret',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status'] ?? null);
    }
}
