<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('can:'.Permission::SettingsManage->value)
        ->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('users/{user}/status', UserStatusController::class)->name('users.status.update');
            Route::get('roles', RoleController::class)->name('roles.index');
        });
});

require __DIR__.'/settings.php';
