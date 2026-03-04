<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Provisioning\SecretBox;
use PHPUnit\Framework\TestCase;

final class SecretBoxTest extends TestCase
{
    private const KEY_A = 'd13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a';
    private const KEY_B = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testEncryptDecryptRoundTrip(): void
    {
        $box = new SecretBox(self::KEY_A);
        $ciphertext = $box->encrypt('hello-world');

        self::assertNotSame('hello-world', $ciphertext);
        self::assertSame('hello-world', $box->decrypt($ciphertext));
    }

    public function testDecryptFailsForInvalidCiphertextFormat(): void
    {
        $box = new SecretBox(self::KEY_A);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid ciphertext');
        $box->decrypt('%%%not-base64%%%');
    }

    public function testDecryptFailsWhenCiphertextWasEncryptedWithAnotherKey(): void
    {
        $ciphertext = (new SecretBox(self::KEY_A))->encrypt('secret');
        $box = new SecretBox(self::KEY_B);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('decryption failed');
        $box->decrypt($ciphertext);
    }
}
