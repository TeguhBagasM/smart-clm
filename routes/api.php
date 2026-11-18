<?php

use App\Http\Controllers\Dcs\AppendixUploadController;
use App\Http\Controllers\Dcs\ClauseController;
use App\Http\Controllers\Dcs\DepartmentController;
use App\Http\Controllers\Dcs\DocTypeController;
use App\Http\Controllers\Dcs\DocumentController;
use App\Http\Controllers\Dcs\DocumentVersionController;
use App\Http\Controllers\Dcs\ExternalReviewController;
use App\Http\Controllers\Dcs\ExtractMetadataController;
use App\Http\Controllers\Dcs\NumberingRuleController;
use App\Http\Controllers\Dcs\NumberPreviewController;
use App\Http\Controllers\Dcs\PublicExternalReviewController;
use App\Http\Controllers\Dcs\ReviewScheduleController;
use App\Http\Controllers\Dcs\VersionComposeController;
use App\Http\Controllers\Dcs\VersionDistributionController;
use App\Http\Controllers\Dcs\VersionDraftController;
use App\Http\Controllers\Dcs\VersionFileController;
use App\Http\Controllers\Dcs\VersionReleaseController;
use App\Http\Controllers\Dcs\VersionReviewController;
use App\Http\Controllers\Dcs\VersionTranslateController;
use App\Http\Controllers\Dcs\VersionWorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| DCS routes (/api/dcs/*) are authenticated with the legacy Node session
| cookie through the `dcs.auth` middleware and registered here as the slices
| of the DCS migration land.
|
*/

// Outside parties reach their review link without an account; the token is the credential.
Route::prefix('dcs-external-review/{token}')->middleware('throttle:60,1')->name('dcs.external-review.')->group(function () {
    Route::get('/', [PublicExternalReviewController::class, 'show'])->name('show');
    Route::post('comments', [PublicExternalReviewController::class, 'comment'])->name('comment');
    Route::post('approve', [PublicExternalReviewController::class, 'approve'])->name('approve');
});

// Reads an uploaded document with AI to suggest its registration data (path kept from the Node API).
Route::post('ai/extract-dcs-metadata', ExtractMetadataController::class)->middleware(['dcs.auth', 'dcs.tenant'])->name('ai.extract-dcs-metadata');

Route::prefix('dcs')->middleware(['dcs.auth', 'dcs.tenant'])->name('dcs.')->group(function () {
    Route::get('doc-types', [DocTypeController::class, 'index'])->name('doc-types.index');
    Route::get('numbering-rule', [NumberingRuleController::class, 'show'])->name('numbering-rule.show');
    Route::get('clauses', [ClauseController::class, 'index'])->name('clauses.index');
    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('preview-number', NumberPreviewController::class)->name('preview-number');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{id}', [DocumentController::class, 'show'])->whereUuid('id')->name('documents.show');
    Route::get('documents/{id}/timeline', [DocumentController::class, 'timeline'])->whereUuid('id')->name('documents.timeline');

    Route::get('versions/{id}/compose', [VersionComposeController::class, 'show'])->whereUuid('id')->name('versions.compose.show');
    Route::get('versions/{id}/draft', [VersionDraftController::class, 'show'])->whereUuid('id')->name('versions.draft.show');

    // Review is open to every authenticated user of the tenant (as in the Node API);
    // the actions decide who may sign, send back or resolve.
    Route::get('versions/{id}/approvals', [VersionReviewController::class, 'approvals'])->whereUuid('id')->name('versions.approvals');
    Route::get('versions/{id}/approval-roster', [VersionReviewController::class, 'roster'])->whereUuid('id')->name('versions.approval-roster');
    Route::get('versions/{id}/comments', [VersionReviewController::class, 'comments'])->whereUuid('id')->name('versions.comments.index');
    Route::post('versions/{id}/comments', [VersionReviewController::class, 'storeComment'])->whereUuid('id')->name('versions.comments.store');
    Route::post('versions/{id}/comments/{cid}/resolve', [VersionReviewController::class, 'resolveComment'])->whereUuid(['id', 'cid'])->name('versions.comments.resolve');
    Route::post('versions/{id}/transition', [VersionWorkflowController::class, 'transition'])->whereUuid('id')->name('versions.transition');
    Route::post('versions/{id}/sign-approve', [VersionWorkflowController::class, 'signApprove'])->whereUuid('id')->name('versions.sign-approve');
    Route::post('versions/{id}/request-changes', [VersionWorkflowController::class, 'requestChanges'])->whereUuid('id')->name('versions.request-changes');

    Route::get('versions/{id}/view', [VersionDistributionController::class, 'view'])->whereUuid('id')->name('versions.view');
    Route::post('versions/{id}/acknowledge', [VersionDistributionController::class, 'acknowledge'])->whereUuid('id')->name('versions.acknowledge');
    Route::get('versions/{id}/receipts', [VersionDistributionController::class, 'receipts'])->whereUuid('id')->name('versions.receipts');

    Route::middleware('can:dcs.release')->group(function () {
        Route::post('versions/{id}/distribute', [VersionDistributionController::class, 'distribute'])->whereUuid('id')->name('versions.distribute');
        Route::post('documents/{id}/versions/{vid}/make-effective', [VersionReleaseController::class, 'makeEffective'])->whereUuid(['id', 'vid'])->name('documents.versions.make-effective');
        Route::post('documents/{id}/versions/{vid}/cancel-release', [VersionReleaseController::class, 'cancelRelease'])->whereUuid(['id', 'vid'])->name('documents.versions.cancel-release');
        Route::post('documents/{id}/versions/{vid}/withdraw', [VersionReleaseController::class, 'withdraw'])->whereUuid(['id', 'vid'])->name('documents.versions.withdraw');
    });

    Route::post('lampiran-upload', [AppendixUploadController::class, 'store'])->name('lampiran-upload');

    Route::middleware('can:dcs.edit')->group(function () {
        Route::post('versions/{id}/translate', [VersionTranslateController::class, 'store'])->whereUuid('id')->name('versions.translate');
        Route::post('versions/{id}/external-review/enable', [ExternalReviewController::class, 'enable'])->whereUuid('id')->name('versions.external-review.enable');
        Route::post('versions/{id}/external-review/disable', [ExternalReviewController::class, 'disable'])->whereUuid('id')->name('versions.external-review.disable');
        Route::post('versions/{id}/external-review/unlock', [ExternalReviewController::class, 'unlock'])->whereUuid('id')->name('versions.external-review.unlock');
        Route::post('versions/{id}/compose', [VersionComposeController::class, 'store'])->whereUuid('id')->name('versions.compose.store');
        Route::post('versions/{id}/file', [VersionFileController::class, 'store'])->whereUuid('id')->name('versions.file.store');
        Route::put('versions/{id}/review-schedule', [ReviewScheduleController::class, 'update'])->whereUuid('id')->name('versions.review-schedule.update');
        Route::patch('versions/{id}/draft', [VersionDraftController::class, 'update'])->whereUuid('id')->name('versions.draft.update');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::post('documents/{id}/versions', [DocumentVersionController::class, 'store'])->whereUuid('id')->name('documents.versions.store');
    });

    Route::post('documents/{id}/release-number', [DocumentController::class, 'releaseNumber'])
        ->whereUuid('id')->middleware('can:dcs.release')->name('documents.release-number');

    Route::middleware('can:dcs.config')->group(function () {
        Route::post('doc-types', [DocTypeController::class, 'store'])->name('doc-types.store');
        Route::put('doc-types/{id}', [DocTypeController::class, 'update'])->name('doc-types.update')->whereUuid('id');
        Route::delete('doc-types/{id}', [DocTypeController::class, 'destroy'])->name('doc-types.destroy')->whereUuid('id');

        Route::put('numbering-rule', [NumberingRuleController::class, 'update'])->name('numbering-rule.update');
        Route::delete('numbering-rule', [NumberingRuleController::class, 'destroy'])->name('numbering-rule.destroy');

        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{id}', [DepartmentController::class, 'update'])->name('departments.update')->whereUuid('id');

        Route::post('clauses', [ClauseController::class, 'store'])->name('clauses.store');
        Route::put('clauses/{id}', [ClauseController::class, 'update'])->name('clauses.update')->whereUuid('id');
        Route::delete('clauses/{id}', [ClauseController::class, 'destroy'])->name('clauses.destroy')->whereUuid('id');
    });
});
