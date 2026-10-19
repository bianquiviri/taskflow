<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\EncryptException;

/**
 * Turns a primary key into the opaque token a URL carries, and back again.
 *
 * The token has to be stable: one record must always produce one URL, or a
 * shared link would stop matching the link the application builds and no
 * redirect could ever be compared. Laravel's `Crypt` cannot be used for this
 * because it draws a random initialisation vector, so it yields a different
 * payload on every call. Here the vector is derived from the plaintext itself
 * and then sealed under the application key — the synthetic IV of an SIV
 * construction — which keeps the token deterministic while still hiding the
 * id. Decryption recomputes that vector, so a token anyone edited stops
 * resolving instead of quietly pointing at another record.
 */
final readonly class IdCipher
{
    private const string CIPHER = 'aes-256-cbc';

    private const int IV_LENGTH = 16;

    public function __construct(private Encrypter $encrypter)
    {
    }

    public function encrypt(int|string $id): string
    {
        $plain = (string) $id;
        $iv = $this->initialisationVector($plain);

        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new EncryptException('Could not encrypt the identifier.');
        }

        return $this->encode($iv.$ciphertext);
    }

    /**
     * The identifier behind a token, or null when the token is not one this
     * application issued.
     */
    public function decrypt(string $token): ?int
    {
        $raw = $this->decode($token);

        if ($raw === null || strlen($raw) <= self::IV_LENGTH || strlen($raw) % self::IV_LENGTH !== 0) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $plain = openssl_decrypt(substr($raw, self::IV_LENGTH), self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($plain === false || ! hash_equals($iv, $this->initialisationVector($plain))) {
            return null;
        }

        return ctype_digit($plain) ? (int) $plain : null;
    }

    private function key(): string
    {
        return $this->encrypter->getKey();
    }

    private function initialisationVector(string $plain): string
    {
        return substr(hash_hmac('sha256', self::CIPHER.':'.$plain, $this->key(), true), 0, self::IV_LENGTH);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $base64 = strtr($value, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }
}
