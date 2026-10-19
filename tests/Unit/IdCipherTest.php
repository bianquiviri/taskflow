<?php

declare(strict_types=1);

use App\Support\IdCipher;

it('turns an id into an opaque url safe token', function () {
    $cipher = app(IdCipher::class);

    $token = $cipher->encrypt(42);

    expect($token)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($token)->not->toContain('42')
        ->and($cipher->decrypt($token))->toBe(42);
});

it('produces the same token for the same id', function () {
    $cipher = app(IdCipher::class);

    // Determinism is the whole point: route() has to yield the same URL on
    // every render, otherwise every shared link and every redirect assertion
    // would change between two calls.
    expect($cipher->encrypt(7))->toBe($cipher->encrypt(7));
});

it('separates one id from the next', function () {
    $cipher = app(IdCipher::class);

    expect($cipher->encrypt(7))->not->toBe($cipher->encrypt(8));
});

it('round trips every id it is given', function () {
    $cipher = app(IdCipher::class);

    foreach ([1, 9, 42, 1234, 999999] as $id) {
        expect($cipher->decrypt($cipher->encrypt($id)))->toBe($id);
    }
});

it('refuses anything that is not a token it issued', function () {
    $cipher = app(IdCipher::class);

    expect($cipher->decrypt(''))->toBeNull()
        ->and($cipher->decrypt('1'))->toBeNull()
        ->and($cipher->decrypt('not-a-token'))->toBeNull()
        ->and($cipher->decrypt($cipher->encrypt(1).'x'))->toBeNull();
});

it('refuses a token whose ciphertext was tampered with', function () {
    $cipher = app(IdCipher::class);
    $token = $cipher->encrypt(42);

    expect($cipher->decrypt(substr($token, 0, -1).'A'))->toBeNull();
});
