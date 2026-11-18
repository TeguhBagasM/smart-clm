<?php

use App\Support\Dcs\NodeSessionToken;

it('accepts a valid session token and returns its identity claims', function () {
    $claims = (new NodeSessionToken('node-secret'))->verify(nodeJwt());

    expect($claims)->toBe(['sub' => 'usr-legal', 'tenantId' => 't-01']);
});

it('rejects an invalid token', function (string $name, callable $token) {
    expect((new NodeSessionToken('node-secret'))->verify($token()))->toBeNull();
})->with([
    'signed with another secret' => ['wrong secret', fn () => nodeJwt(secret: 'other-secret')],
    'expired' => ['expired', fn () => nodeJwt(['exp' => time() - 10])],
    'without expiry' => ['no exp', fn () => nodeJwt(['exp' => null])],
    '2FA challenge token' => ['purpose', fn () => nodeJwt(['purpose' => '2fa-pending'])],
    'without tenant claim' => ['no tenant', fn () => nodeJwt(['tenantId' => null])],
    'without subject' => ['no sub', fn () => nodeJwt(['sub' => ''])],
    'unsigned alg none' => ['alg none', fn () => nodeJwt(alg: 'none')],
    'another algorithm' => ['HS512 header', fn () => nodeJwt(alg: 'HS512')],
    'not a jwt' => ['garbage', fn () => 'not-a-token'],
    'missing token' => ['null', fn () => null],
]);

it('rejects a token whose payload was tampered with', function () {
    [$header, , $signature] = explode('.', nodeJwt());
    $forged = rtrim(strtr(base64_encode(json_encode(['sub' => 'usr-admin', 'tenantId' => 't-01', 'exp' => time() + 3600])), '+/', '-_'), '=');

    expect((new NodeSessionToken('node-secret'))->verify("{$header}.{$forged}.{$signature}"))->toBeNull();
});

it('rejects every token when no secret is configured', function (?string $secret) {
    expect((new NodeSessionToken($secret))->verify(nodeJwt(secret: (string) $secret)))->toBeNull();
})->with([null, '']);
