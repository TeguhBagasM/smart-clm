<?php

use App\Enums\SystemRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['dcs.node_jwt_secret' => 'node-secret']);

    Route::middleware('dcs.auth')->get('api/dcs/_probe', fn () => response()->json([
        'user' => Auth::id(),
        'legacyId' => Auth::user()->legacy_id,
    ]));
});

function legacyUser(array $userAttributes = [], array $tenantAttributes = []): User
{
    $tenant = Tenant::factory()->create(['legacy_id' => 't-01', ...$tenantAttributes]);

    return User::factory()->for($tenant)->role(SystemRole::Legal)->create(['legacy_id' => 'usr-legal', ...$userAttributes]);
}

function probe(?string $token = null): TestResponse
{
    return test()->withCredentials()->withUnencryptedCookie('clm_token', $token ?? nodeJwt())->getJson('/api/dcs/_probe');
}

it('signs in the Laravel user mapped to the Node session cookie', function () {
    $user = legacyUser();

    probe()->assertOk()->assertJson(['user' => $user->id, 'legacyId' => 'usr-legal']);
});

it('answers 401 in the DCS error shape without a cookie', function () {
    legacyUser();

    $this->getJson('/api/dcs/_probe')->assertUnauthorized()->assertExactJson(['error' => 'Sesi tidak valid atau kadaluarsa. Silakan login.']);
});

it('answers 401 for a token that is invalid or expired', function (array $claims, string $secret) {
    legacyUser();

    probe(nodeJwt($claims, $secret))->assertUnauthorized();
})->with([
    'wrong secret' => [[], 'other'],
    'expired' => [['exp' => time() - 1], 'node-secret'],
    '2FA challenge' => [['purpose' => '2fa-pending'], 'node-secret'],
]);

it('answers 401 when no Laravel user carries the token subject', function () {
    legacyUser(['legacy_id' => 'usr-someone-else']);

    probe()->assertUnauthorized();
});

it('answers 401 for a deactivated user or tenant', function (array $user, array $tenant) {
    legacyUser($user, $tenant);

    probe()->assertUnauthorized();
})->with([
    'inactive user' => [['is_active' => false], []],
    'inactive tenant' => [[], ['is_active' => false]],
]);

it('answers 401 when the token tenant differs from the mapped user tenant', function () {
    legacyUser();

    probe(nodeJwt(['tenantId' => 't-99']))->assertUnauthorized();
});

it('lets a super admin without tenant through regardless of the token tenant', function () {
    User::factory()->superAdmin()->create(['legacy_id' => 'usr-legal']);

    probe(nodeJwt(['tenantId' => 't-77']))->assertOk();
});

it('does not accept the Laravel web session on its own', function () {
    $user = legacyUser();

    $this->actingAs($user)->getJson('/api/dcs/_probe')->assertUnauthorized();
});
