<?php

use App\Actions\Dcs\ComposeVersion;
use App\Actions\Dcs\SaveDraftContent;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);

    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->tenant = $this->admin->tenant;
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
    $this->version = DocumentVersion::factory()->for($this->document)->create(['status' => VersionStatus::Draft, 'created_by_id' => $this->admin->id]);
});

it('keeps the flow image of an earlier save when composing again without one', function () {
    $image = UploadedFile::fake()->image('flow.png', 40, 40);
    app(ComposeVersion::class)->handle($this->admin, $this->tenant, $this->version, ['flowMode' => 'image'], $image);
    app(ComposeVersion::class)->handle($this->admin, $this->tenant, $this->version, ['purpose' => 'Baru'], null);

    $content = $this->version->content()->first();

    expect($content->flow_image_key)->not->toBeNull()->and($content->draft_revision)->toBe(2);
    Storage::disk('dcs')->assertExists($content->flow_image_key);
    expect($this->engine->composed[1]['flow']['mode'])->toBe('image');
});

it('deletes the previous flow image when a new one replaces it', function () {
    $save = fn () => app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, ['flowMode' => 'image'], UploadedFile::fake()->image('f.png'));
    $first = $save()->flow_image_key;
    $second = $save()->flow_image_key;

    Storage::disk('dcs')->assertMissing($first);
    Storage::disk('dcs')->assertExists($second);
});

it('snapshots the signature columns and defaults the first signer to the document owner', function () {
    app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, ['purpose' => 'x']);

    $steps = $this->version->steps()->get();

    expect($steps)->not->toBeEmpty()->and($steps->first()->assigned_user_id)->toBe($this->admin->id);
});

it('assigns signers by legacy id, adds extra reviewers, and refuses unknown signers', function () {
    $legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create(['legacy_id' => 'usr-legal']);
    $save = fn (array $content) => app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, $content);

    $save(['signatureAssignments' => ['usr-legal'], 'extraReviewers' => [['label' => 'Auditor', 'role' => null, 'assignedUserId' => 'usr-legal']]]);

    $steps = $this->version->steps()->get();
    expect($steps->first()->assigned_user_id)->toBe($legal->id)
        ->and($steps->last()->label)->toBe('Auditor')->and($steps->last()->group_heading)->toBe('Reviewer Tambahan:');

    expect(fn () => $save(['signatureAssignments' => ['usr-nobody']]))->toThrow(ValidationException::class);
});

it('does not touch the steps when only unrelated parts are saved', function () {
    $save = fn (array $content) => app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, $content);
    $save(['signatureAssignments' => [], 'extraReviewers' => [['label' => 'Extra', 'role' => null, 'assignedUserId' => null]]]);
    $before = $this->version->steps()->pluck('id')->all();

    $save(['purpose' => 'Hanya tujuan']);

    expect($this->version->steps()->pluck('id')->all())->toBe($before);
});

it('hands the engine the tenant branding and the revision history', function () {
    TenantSetting::query()->forceCreate(['tenant_id' => $this->tenant->id, 'company_name' => 'PT Contoh', 'company_address' => 'Jl. Satu', 'confidentiality_notice' => 'Rahasia']);
    $this->version->forceFill(['major' => 2, 'minor' => 0])->save();
    DocumentVersion::factory()->for($this->document)->create(['major' => 1, 'minor' => 9, 'status' => VersionStatus::Superseded, 'effective_at' => '2026-01-05 00:00:00', 'change_summary' => 'Awal']);

    app(ComposeVersion::class)->handle($this->admin, $this->tenant, $this->version, ['purpose' => 'x'], null);

    $input = $this->engine->composed[0];
    expect($input['confidentialityNotice'])->toBe('Rahasia')
        ->and($input['revisionHistory'])->toHaveCount(1)
        ->and($input['issuedDateIso'])->toStartWith('2026-01-05')
        ->and($input['statusLabel'])->toBe('Draft');
});
