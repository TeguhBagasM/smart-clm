<?php

use App\Enums\SystemRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('modules.show', ['module' => 'ai-tools', 'page' => 'opini-hukum']))->assertRedirect(route('login'));
});

test('company users cannot open modules still in development', function () {
    $this->actingAs(User::factory()->role(SystemRole::Admin)->create())
        ->get(route('modules.show', ['module' => 'ai-tools', 'page' => 'opini-hukum']))
        ->assertForbidden();
});

test('a super admin opens the placeholder page of a module', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('modules.show', ['module' => 'ai-tools', 'page' => 'opini-hukum']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('modules/InDevelopment')
            ->where('module', 'AI Tools Hukum')
            ->where('page', 'Opini Hukum'));
});

test('unknown modules and pages are not found', function (string $module, string $page) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get("/modules/{$module}/{$page}")
        ->assertNotFound();
})->with([
    'unknown module' => ['nope', 'dashboard'],
    'unknown page' => ['ai-tools', 'nope'],
    'finished menu is not a placeholder' => ['dokumen-internal', 'tugas-saya'],
]);
