<?php

use App\Enums\MasterData\MasterList;
use App\Enums\Permission;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\AiTools\DocumentComparisonController;
use App\Http\Controllers\Documents\Config\ClauseConfigController;
use App\Http\Controllers\Documents\Config\DepartmentConfigController;
use App\Http\Controllers\Documents\Config\DocTypeConfigController;
use App\Http\Controllers\Documents\Config\HeaderAssetController;
use App\Http\Controllers\Documents\Config\NumberingConfigController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentCreateController;
use App\Http\Controllers\Documents\DocumentDraftController;
use App\Http\Controllers\Documents\DocumentNumberController;
use App\Http\Controllers\Documents\DocumentRevisionController;
use App\Http\Controllers\Documents\DocumentTimelineController;
use App\Http\Controllers\Documents\ExternalReviewPageController;
use App\Http\Controllers\Documents\TaskQueueController;
use App\Http\Controllers\Documents\VersionCommentController;
use App\Http\Controllers\Documents\VersionDistributionController;
use App\Http\Controllers\Documents\VersionExternalReviewController;
use App\Http\Controllers\Documents\VersionFileController;
use App\Http\Controllers\Documents\VersionReleaseController;
use App\Http\Controllers\Documents\VersionReviewScheduleController;
use App\Http\Controllers\Documents\VersionSignerController;
use App\Http\Controllers\Documents\VersionTranslationController;
use App\Http\Controllers\Documents\VersionWorkflowController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MasterData\DocumentTypeController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\FormOptionsController;
use App\Http\Controllers\MasterData\LookupRecordController;
use App\Http\Controllers\MasterData\MasterDataIndexController;
use App\Http\Controllers\MasterData\MasterListController;
use App\Http\Controllers\MasterData\MasterRecordStateController;
use App\Http\Controllers\MasterData\PartnerController;
use App\Http\Controllers\MasterData\PartnerGroupController;
use App\Http\Controllers\MasterData\VendorController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Middleware\ProtectReviewLink;
use App\Models\AiTools\DocumentComparison;
use App\Models\Dcs\Document;
use App\Models\MasterData\Employee;
use App\Models\MasterData\Vendor;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Outside parties open their review link without an account; the token is the credential.
Route::prefix('tinjau/dcs/{token}')->middleware(['throttle:60,1', ProtectReviewLink::class])->where(['token' => '[A-Za-z0-9]+'])->name('external-review.')->group(function () {
    Route::get('/', [ExternalReviewPageController::class, 'show'])->name('show');
    Route::post('komentar', [ExternalReviewPageController::class, 'comment'])->name('comment');
    Route::post('setuju', [ExternalReviewPageController::class, 'approve'])->name('approve');
});

// `{record}` of a master data route is the row of the list named by `{list}` or by the first path
// segment after master-data (document-types, partner-groups, partners, employees, vendors). Another
// company's row is found here and then refused by the policy with 404, so nothing leaks.
Route::bind('record', function (string $value, RouteInstance $route) {
    $key = $route->parameter('list') ?? (explode('/', $route->uri())[1] ?? null);
    $key = $key instanceof MasterList ? $key->value : (string) $key;
    $model = ['employees' => Employee::class, 'vendors' => Vendor::class][$key] ?? MasterList::tryFrom($key)?->model() ?? abort(404);

    return $model::query()->findOrFail($value);
});

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('modules/{module}/{page}', ModulePlaceholderController::class)
        ->middleware('can:'.NavigationMenu::InDevelopmentAbility)
        ->where(['module' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+'])
        ->name('modules.show');

    Route::prefix('ai-tools/comparisons')->name('ai-tools.comparisons.')->middleware('can:viewAny,'.DocumentComparison::class)->group(function () {
        Route::get('/', [DocumentComparisonController::class, 'index'])->name('index');
        Route::get('create', [DocumentComparisonController::class, 'create'])->name('create');
        Route::get('archive', [DocumentComparisonController::class, 'archive'])->name('archive');
        Route::post('/', [DocumentComparisonController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('{comparison:uuid}', [DocumentComparisonController::class, 'show'])->whereUuid('comparison')->name('show');
        Route::delete('{comparison:uuid}', [DocumentComparisonController::class, 'destroy'])->whereUuid('comparison')->name('destroy');
    });

    Route::prefix('master-data')->name('master-data.')->group(function () {
        $simple = collect(MasterList::cases())->filter(fn (MasterList $list): bool => $list->isSimple())->map(fn (MasterList $list): string => $list->value)->all();

        Route::get('/', MasterDataIndexController::class)->name('index');
        Route::get('options/{kind}', [FormOptionsController::class, 'show'])
            ->whereIn('kind', ['platforms', 'partners', 'categories', 'document-types', 'employment-types', 'party-types', 'currencies', 'departments', 'employees', 'vendors'])->name('options');

        foreach (['employees' => EmployeeController::class, 'vendors' => VendorController::class] as $path => $controller) {
            Route::get($path, [$controller, 'index'])->name("{$path}.index");
            Route::post($path, [$controller, 'store'])->name("{$path}.store");
            Route::put("{$path}/{record}", [$controller, 'update'])->whereNumber('record')->name("{$path}.update");
            Route::patch("{$path}/{record}/deactivate", [$controller, 'deactivate'])->whereNumber('record')->name("{$path}.deactivate");
            Route::patch("{$path}/{record}/reactivate", [$controller, 'reactivate'])->whereNumber('record')->name("{$path}.reactivate");
        }

        foreach (['document-types' => DocumentTypeController::class, 'partner-groups' => PartnerGroupController::class, 'partners' => PartnerController::class] as $path => $controller) {
            Route::post($path, [$controller, 'store'])->name("{$path}.store");
            Route::put("{$path}/{record}", [$controller, 'update'])->whereNumber('record')->name("{$path}.update");
        }

        Route::post('{list}', [LookupRecordController::class, 'store'])->whereIn('list', $simple)->name('lookups.store');
        Route::put('{list}/{record}', [LookupRecordController::class, 'update'])->whereIn('list', $simple)->whereNumber('record')->name('lookups.update');

        Route::get('{list}', [MasterListController::class, 'index'])->name('lists.index');
        Route::put('{list}/order', [MasterListController::class, 'reorder'])->name('lists.reorder');
        Route::patch('{list}/{record}/deactivate', [MasterRecordStateController::class, 'deactivate'])->whereNumber('record')->name('records.deactivate');
        Route::patch('{list}/{record}/reactivate', [MasterRecordStateController::class, 'reactivate'])->whereNumber('record')->name('records.reactivate');
    });

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');

    Route::prefix('documents/config')->name('documents.config.')->middleware('can:configure,'.Document::class)->group(function () {
        Route::redirect('/', '/documents/config/types');

        Route::get('types', [DocTypeConfigController::class, 'index'])->name('types.index');
        Route::get('types/create', [DocTypeConfigController::class, 'create'])->name('types.create');
        Route::post('types', [DocTypeConfigController::class, 'store'])->name('types.store');
        Route::get('types/{type}/edit', [DocTypeConfigController::class, 'edit'])->whereUuid('type')->name('types.edit');
        Route::put('types/{type}', [DocTypeConfigController::class, 'update'])->whereUuid('type')->name('types.update');
        Route::delete('types/{type}', [DocTypeConfigController::class, 'destroy'])->whereUuid('type')->name('types.destroy');
        Route::post('types/{type}/restore', [DocTypeConfigController::class, 'restore'])->whereUuid('type')->name('types.restore');

        Route::post('header-assets', [HeaderAssetController::class, 'store'])->middleware('throttle:20,1')->name('header-assets.store');
        Route::get('header-assets/company-logo', [HeaderAssetController::class, 'company'])->name('header-assets.company');
        Route::get('header-assets/{asset}', [HeaderAssetController::class, 'show'])->whereUuid('asset')->name('header-assets.show');
        Route::put('brand-colors', [HeaderAssetController::class, 'colors'])->name('brand-colors.update');

        Route::get('clauses', [ClauseConfigController::class, 'index'])->name('clauses.index');
        Route::get('clauses/create', [ClauseConfigController::class, 'create'])->name('clauses.create');
        Route::post('clauses', [ClauseConfigController::class, 'store'])->name('clauses.store');
        Route::get('clauses/{clause}/edit', [ClauseConfigController::class, 'edit'])->whereUuid('clause')->name('clauses.edit');
        Route::put('clauses/{clause}', [ClauseConfigController::class, 'update'])->whereUuid('clause')->name('clauses.update');
        Route::delete('clauses/{clause}', [ClauseConfigController::class, 'destroy'])->whereUuid('clause')->name('clauses.destroy');
        Route::post('clauses/{clause}/restore', [ClauseConfigController::class, 'restore'])->whereUuid('clause')->name('clauses.restore');

        Route::get('departments', [DepartmentConfigController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentConfigController::class, 'store'])->name('departments.store');
        Route::put('departments/{department}', [DepartmentConfigController::class, 'update'])->whereUuid('department')->name('departments.update');

        Route::get('numbering', [NumberingConfigController::class, 'index'])->name('numbering.index');
        Route::get('numbering/preview', [NumberingConfigController::class, 'preview'])->name('numbering.preview');
        Route::put('numbering', [NumberingConfigController::class, 'update'])->name('numbering.update');
        Route::delete('numbering/{type}', [NumberingConfigController::class, 'destroy'])->whereUuid('type')->name('numbering.destroy');
    });

    Route::middleware('can:create,'.Document::class)->group(function () {
        Route::get('documents/create', [DocumentCreateController::class, 'create'])->name('documents.create');
        Route::post('documents', [DocumentCreateController::class, 'store'])->name('documents.store');
        Route::get('documents/number-preview', [DocumentCreateController::class, 'numberPreview'])->name('documents.number-preview');
        Route::post('documents/extract-metadata', [DocumentCreateController::class, 'extractMetadata'])->name('documents.extract-metadata');
        Route::post('documents/appendix-upload', [DocumentCreateController::class, 'appendixUpload'])->name('documents.appendix-upload');
    });

    Route::get('documents/queue', [TaskQueueController::class, 'index'])->middleware('can:viewQueue,'.Document::class)->name('documents.queue');

    Route::prefix('documents/{document:uuid}')->scopeBindings()->group(function () {
        Route::get('/', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('timeline', [DocumentTimelineController::class, 'index'])->name('documents.timeline');
        Route::post('versions', [DocumentRevisionController::class, 'store'])->middleware('can:'.Permission::DcsEdit->value)->name('documents.versions.store');
        Route::post('release-number', [DocumentNumberController::class, 'release'])->middleware('can:'.Permission::DcsRelease->value)->name('documents.release-number');
        Route::get('versions/{version:uuid}/file', [VersionFileController::class, 'show'])->name('documents.versions.file');

        Route::prefix('versions/{version:uuid}')->name('documents.versions.')->group(function () {
            Route::middleware('can:'.Permission::DcsEdit->value)->group(function () {
                Route::get('edit', [DocumentDraftController::class, 'edit'])->name('edit');
                Route::patch('draft', [DocumentDraftController::class, 'update'])->name('draft.update');
                Route::post('render', [DocumentDraftController::class, 'render'])->name('render');
                Route::post('file', [DocumentDraftController::class, 'uploadFile'])->name('upload');
                Route::put('review-schedule', [VersionReviewScheduleController::class, 'update'])->name('review-schedule');
                Route::post('external-review', [VersionExternalReviewController::class, 'enable'])->name('external-review.enable');
                Route::post('external-review/disable', [VersionExternalReviewController::class, 'disable'])->name('external-review.disable');
                Route::post('external-review/unlock', [VersionExternalReviewController::class, 'unlock'])->name('external-review.unlock');
                Route::post('translate', [VersionTranslationController::class, 'store'])->name('translate');
            });

            Route::post('submit', [VersionWorkflowController::class, 'submit'])->name('submit');
            Route::post('sign', [VersionWorkflowController::class, 'sign'])->name('sign');
            Route::post('request-changes', [VersionWorkflowController::class, 'requestChanges'])->name('request-changes');
            Route::post('return-to-draft', [VersionWorkflowController::class, 'returnToDraft'])->name('return-to-draft');
            Route::post('signers/{order}', [VersionSignerController::class, 'reassign'])->whereNumber('order')->name('signers.reassign');
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
require __DIR__.'/contract-library.php';
