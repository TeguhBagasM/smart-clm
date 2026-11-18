<?php

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

it('creates the user in the actor tenant and sends an invitation', function () {
    Notification::fake();
    $admin = User::factory()->role(Role::Admin)->create();

    $user = app(CreateUser::class)->handle($admin, [
        'name' => 'Rina',
        'email' => 'rina@example.com',
        'role' => Role::Legal->value,
        'tenant_id' => null,
    ]);

    expect($user->tenant_id)->toBe($admin->tenant_id)
        ->and($user->role)->toBe(Role::Legal)
        ->and($user->is_active)->toBeTrue();
    Notification::assertSentTo($user, UserInvitation::class);
});

it('places users created by a super admin in the chosen tenant', function () {
    Notification::fake();
    $tenant = Tenant::factory()->create();

    $user = app(CreateUser::class)->handle(User::factory()->superAdmin()->create(), [
        'name' => 'Rina',
        'email' => 'rina@example.com',
        'role' => Role::Admin->value,
        'tenant_id' => $tenant->id,
    ]);

    expect($user->tenant_id)->toBe($tenant->id);
});

it('links the invitation to a valid password reset token', function () {
    $user = User::factory()->create();

    $mail = (new UserInvitation)->toMail($user);
    $token = Str::of(parse_url($mail->actionUrl, PHP_URL_PATH))->afterLast('/')->toString();

    expect($mail->actionUrl)->toContain(urlencode($user->email))
        ->and(Password::broker()->tokenExists($user, $token))->toBeTrue();
});
