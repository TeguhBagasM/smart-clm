<?php

use App\Enums\SystemRole;
use App\Models\User;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Support\Facades\Route;

/**
 * @return array<string, list<string>> module title => item titles
 */
function menuFor(User $user): array
{
    return collect(app(NavigationMenu::class)->for($user))
        ->mapWithKeys(fn (array $module): array => [$module['title'] => array_column($module['items'], 'title')])
        ->all();
}

test('a super admin sees every module, placeholders included, but no company screens', function () {
    $menu = menuFor(User::factory()->superAdmin()->create());

    expect(array_keys($menu))->toBe(['Dokumen Eksternal', 'Dokumen Internal', 'Arsip & Daftar', 'Master Data', 'AI Tools Hukum', 'Pengaturan Sistem'])
        ->and($menu['Dokumen Internal'])->toBe(['Dashboard', 'Dokumen Terkontrol', 'Dokumen Karyawan'])
        ->and($menu['Master Data'])->not->toContain('Pengaturan Dokumen')
        ->and($menu['Pengaturan Sistem'])->toContain('Pengguna', 'Hak Akses', 'Kelola Perusahaan');

    foreach ($menu as $items) {
        expect($items[0])->toBe('Dashboard');
    }
});

test('a company user sees only finished menus they may open', function (SystemRole $role, array $expected) {
    $user = User::factory()->role($role)->create();
    $modules = app(NavigationMenu::class)->for($user);

    expect(menuFor($user))->toBe($expected)
        ->and(collect($modules)->flatMap(fn (array $module): array => $module['items'])->where('in_development', true))->toBeEmpty();
})->with([
    'admin' => [SystemRole::Admin, [
        'Dokumen Internal' => ['Tugas saya', 'Dokumen Terkontrol'],
        'Master Data' => ['Pengaturan Dokumen'],
        'Pengaturan Sistem' => ['Pengguna', 'Hak Akses'],
    ]],
    'viewer' => [SystemRole::Viewer, [
        'Dokumen Internal' => ['Tugas saya', 'Dokumen Terkontrol'],
    ]],
]);

test('every menu points at an existing page', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => '/'.ltrim($route->uri(), '/'));

    foreach (app(NavigationMenu::class)->for(User::factory()->role(SystemRole::Admin)->create()) as $module) {
        foreach ($module['items'] as $item) {
            expect($routes)->toContain($item['href']);
        }
    }

    $placeholder = app(NavigationMenu::class)->for(User::factory()->superAdmin()->create())[0]['items'][0];

    expect($placeholder)->toMatchArray(['href' => '/modules/dokumen-eksternal/dashboard', 'in_development' => true]);
});
