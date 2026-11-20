<?php

use App\Http\Controllers\Templates\ClauseController;
use App\Http\Controllers\Templates\ClauseDraftController;
use App\Http\Controllers\Templates\TemplateController;
use App\Http\Controllers\Templates\TemplateDraftController;
use App\Http\Controllers\Templates\VariableController;
use Illuminate\Support\Facades\Route;

/*
 * Klausul & Template (contract library). Every route needs a login; what each person may do is
 * decided by the policies in App\Policies\Templates (templates.manage, own company; another
 * company answers 404) and by the Actions. Route parameter `record` is the row (implicit binding).
 */
Route::middleware(['auth'])->prefix('contract-library')->name('contract-library.')->group(function () {
    Route::prefix('clauses')->name('clauses.')->group(function () {
        Route::get('/', [ClauseController::class, 'index'])->name('index');
        Route::post('/', [ClauseController::class, 'store'])->name('store');
        Route::post('draft-with-ai', [ClauseDraftController::class, 'store'])->middleware('throttle:20,1')->name('draft');
        Route::put('{clause}', [ClauseController::class, 'update'])->name('update');
        Route::patch('{clause}/deactivate', [ClauseController::class, 'deactivate'])->name('deactivate');
        Route::patch('{clause}/reactivate', [ClauseController::class, 'reactivate'])->name('reactivate');
    });

    Route::prefix('fields')->name('fields.')->group(function () {
        Route::get('/', [VariableController::class, 'index'])->name('index');
        Route::post('/', [VariableController::class, 'store'])->name('store');
        Route::put('{field}', [VariableController::class, 'update'])->name('update');
        Route::patch('{field}/deactivate', [VariableController::class, 'deactivate'])->name('deactivate');
        Route::patch('{field}/reactivate', [VariableController::class, 'reactivate'])->name('reactivate');
    });

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::get('{template}', [TemplateController::class, 'show'])->name('show');
        Route::put('{template}', [TemplateController::class, 'update'])->name('update');
        Route::patch('{template}/deactivate', [TemplateController::class, 'deactivate'])->name('deactivate');
        Route::patch('{template}/reactivate', [TemplateController::class, 'reactivate'])->name('reactivate');
        Route::post('{template}/duplicate', [TemplateController::class, 'duplicate'])->name('duplicate');
        Route::post('{template}/draft', [TemplateController::class, 'startDraft'])->name('draft.start');
        Route::put('{template}/draft', [TemplateDraftController::class, 'save'])->name('draft.save');
        Route::put('{template}/draft/order', [TemplateDraftController::class, 'reorder'])->name('draft.order');
        Route::get('{template}/preview', [TemplateDraftController::class, 'preview'])->name('preview');
        Route::post('{template}/publish', [TemplateDraftController::class, 'publish'])->name('publish');
    });
});
