<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentTimelineController;
use App\Http\Controllers\Documents\VersionCommentController;
use App\Http\Controllers\Documents\VersionDistributionController;
use App\Http\Controllers\Documents\VersionFileController;
use App\Http\Controllers\Documents\VersionReleaseController;
use App\Http\Controllers\Documents\VersionWorkflowController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');

    Route::prefix('documents/{document:uuid}')->scopeBindings()->group(function () {
        Route::get('/', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('timeline', [DocumentTimelineController::class, 'index'])->name('documents.timeline');
        Route::get('versions/{version:uuid}/file', [VersionFileController::class, 'show'])->name('documents.versions.file');

        Route::prefix('versions/{version:uuid}')->name('documents.versions.')->group(function () {
            Route::post('submit', [VersionWorkflowController::class, 'submit'])->name('submit');
            Route::post('sign', [VersionWorkflowController::class, 'sign'])->name('sign');
            Route::post('request-changes', [VersionWorkflowController::class, 'requestChanges'])->name('request-changes');
            Route::post('return-to-draft', [VersionWorkflowController::class, 'returnToDraft'])->name('return-to-draft');
            Route::post('comments', [VersionCommentController::class, 'store'])->name('comments.store');
            Route::post('comments/{comment}/resolve', [VersionCommentController::class, 'resolve'])->whereUuid('comment')->name('comments.resolve');
            Route::post('acknowledge', [VersionDistributionController::class, 'acknowledge'])->name('acknowledge');

            Route::middleware('can:'.Permission::DcsRelease->value)->group(function () {
                Route::post('release', [VersionReleaseController::class, 'store'])->name('release');
                Route::post('cancel-release', [VersionReleaseController::class, 'cancel'])->name('cancel-release');
                Route::post('withdraw', [VersionReleaseController::class, 'withdraw'])->name('withdraw');
                Route::post('distribute', [VersionDistributionController::class, 'store'])->name('distribute');
            });
        });
    });

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
