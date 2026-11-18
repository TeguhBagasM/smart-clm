<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Id of the seeded system role, for request payloads.
 */
function systemRoleId(SystemRole $role): int
{
    return Role::systemRole($role)->id;
}

/**
 * A JWT like the one the legacy Node app issues (HS256), for DCS auth tests.
 *
 * @param  array<string, mixed>  $claims
 */
function nodeJwt(array $claims = [], string $secret = 'node-secret', string $alg = 'HS256'): string
{
    $encode = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    $header = $encode(json_encode(['alg' => $alg, 'typ' => 'JWT']));
    $payload = $encode(json_encode([
        'sub' => 'usr-legal',
        'tenantId' => 't-01',
        'role' => 'legal',
        'iat' => time(),
        'exp' => time() + 3600,
        ...$claims,
    ]));
    $signature = $encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

    return "{$header}.{$payload}.{$signature}";
}

/**
 * A DCS API client authenticated as the given user through a Node-style session
 * cookie. Gives the user and tenant legacy ids on the fly.
 */
function dcsAs(User $user): TestCase
{
    config(['dcs.node_jwt_secret' => 'node-secret']);

    $user->forceFill(['legacy_id' => $user->legacy_id ?? 'usr-'.$user->id])->save();
    $tenant = $user->tenant;
    $tenant?->forceFill(['legacy_id' => $tenant->legacy_id ?? 't-'.$tenant->id])->save();

    return test()->withCredentials()->withUnencryptedCookie('clm_token', nodeJwt([
        'sub' => $user->legacy_id,
        'tenantId' => $tenant?->legacy_id ?? 't-0',
    ]));
}
