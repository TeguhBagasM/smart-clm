<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Documents\DocumentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:'.Permission::SettingsManage->value)->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('users/{user}/status', UserStatusController::class)->name('users.status.update');
        });

        Route::middleware('can:'.Permission::RolesView->value)->group(function () {
            Route::resource('roles', RoleController::class)->except(['show']);
        });
    });
});

require __DIR__.'/settings.php';
