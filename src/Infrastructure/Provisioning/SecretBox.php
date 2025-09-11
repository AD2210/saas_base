<?php
namespace App\Infrastructure\Provisioning;
final class SecretBox {
    private string $key;
    public function __construct(string $hexKey) {
        if (!\function_exists('sodium_crypto_secretbox')) { throw new \RuntimeException('libsodium is required'); }
        $this->key = sodium_hex2bin($hexKey);
    }
    public function encrypt(string $plaintext): string {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return base64_encode($nonce.$cipher);
    }
    public function decrypt(string $packed): string {
        $raw = base64_decode($packed, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { throw new \RuntimeException('invalid ciphertext'); }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
    }
}