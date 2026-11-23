<?php

use App\Actions\Dcs\StoreRendition;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Queue\TaskQueue;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    app()->instance(PdfEngine::class, new FakePdfEngine);
    $this->notifier = new RecordingNotifier;
    app()->instance(DcsNotifier::class, $this->notifier);
});

/**
 * A document under review whose three columns name people: the creator signs the
 * first, Rina the second and Budi the third. Rina has a colleague with the same role.
 *
 * @param  array<int, array{mode?: string, user?: string|null, allow_creator?: bool}>  $overrides  by column (1-based)
 * @return array<string, mixed>
 */
function personalFixture(array $overrides = []): array
{
    $tenant = Tenant::factory()->create();
    $creator = User::factory()->for($tenant)->role(SystemRole::Admin)->create(['name' => 'Pembuat']);
    $rina = User::factory()->for($tenant)->role(SystemRole::Legal)->create(['name' => 'Rina']);
    $colleague = User::factory()->for($tenant)->role(SystemRole::Legal)->create(['name' => 'Rekan Rina']);
    $budi = User::factory()->for($tenant)->role(SystemRole::Admin)->create(['name' => 'Budi']);
    $document = Document::factory()->for($tenant)->create(['owner_user_id' => $creator->id, 'created_by_id' => $creator->id]);
    $version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::UnderReview, 'created_by_id' => $creator->id]);
    $people = ['creator' => $creator, 'rina' => $rina, 'budi' => $budi];

    foreach ([1 => ['Dibuat oleh', 'creator'], 2 => ['Diperiksa oleh', 'rina'], 3 => ['Disetujui oleh', 'budi']] as $position => [$label, $who]) {
        $o = $overrides[$position] ?? [];
        $user = array_key_exists('user', $o) ? $o['user'] : $who;

        VersionStep::query()->forceCreate([
            'version_id' => $version->id, 'group_position' => 1, 'group_heading' => null, 'position' => $position, 'label' => $label,
            'role_id' => systemRoleId(SystemRole::Legal), 'role_name' => null,
            'assigned_user_id' => $user === null ? null : $people[$user]->id,
            'fill_mode' => $o['mode'] ?? 'person', 'allow_creator' => $o['allow_creator'] ?? false, 'status' => 'pending',
        ]);
    }

    VersionContent::query()->forceCreate(['version_id' => $version->id, 'language_mode' => 'single', 'flow_mode' => 'none', 'flow_display_style' => 'diagram', 'flow_lanes' => [], 'flow_steps' => [], 'render_layout' => ['signatureSheetPageIndex' => 1, 'sigLayout' => ['colW' => 170], 'headerLayout' => null], 'draft_revision' => 1]);
    app(StoreRendition::class)->handle($creator, $version, RenditionKind::Composed, FakePdfEngine::pdf());

    return compact('tenant', 'creator', 'rina', 'colleague', 'budi', 'document', 'version');
}

/**
 * Signs through the document page and answers what the toast said.
 *
 * @return array{type: string, message: string}
 */
function signThroughPage(User $user, DocumentVersion $version): array
{
    test()->actingAs($user)->post(route('documents.versions.sign', [$version->document, $version]))->assertRedirect();

    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

function signed(User $user, DocumentVersion $version): void
{
    expect(signThroughPage($user, $version)['type'])->toBe('success');
}

function refused(User $user, DocumentVersion $version, ?string $message = null): void
{
    $toast = signThroughPage($user, $version);

    expect($toast['type'])->toBe('error');

    if ($message !== null) {
        expect($toast['message'])->toBe($message);
    }
}

describe('only the named person signs', function () {
    it('refuses a colleague with the same role and department, and lets the named person sign', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'version' => $version] = personalFixture();

        signed($creator, $version);
        refused($colleague, $version);
        expect(Approval::query()->where('version_id', $version->id)->count())->toBe(1);

        signed($rina, $version);
        expect(Approval::query()->where('version_id', $version->id)->count())->toBe(2);
    });

    it('closes a new-style column that has nobody yet, even to holders of its role', function () {
        ['creator' => $creator, 'rina' => $rina, 'version' => $version] = personalFixture([2 => ['user' => null]]);

        signed($creator, $version);
        refused($rina, $version);
    });

    it('keeps the old rule for a legacy column: anyone holding its role may sign', function () {
        ['creator' => $creator, 'colleague' => $colleague, 'version' => $version] = personalFixture([2 => ['mode' => 'legacy', 'user' => null]]);

        signed($creator, $version);
        signed($colleague, $version);
    });

    it('gives the task only to the named person', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'version' => $version] = personalFixture();
        signed($creator, $version);

        $queue = app(TaskQueue::class);

        expect($queue->counts($rina)['sign'] ?? null)->toBe(1)
            ->and($queue->counts($colleague)['sign'] ?? null)->toBe(0);
    });
});

describe('the creator', function () {
    it('cannot approve their own document after the first column, unless the column allows it', function () {
        ['creator' => $creator, 'rina' => $rina, 'version' => $version] = personalFixture([3 => ['user' => 'creator']]);

        signed($creator, $version);
        signed($rina, $version);
        refused($creator, $version, 'Pembuat dokumen tidak boleh menyetujui dokumennya sendiri.');

        ['creator' => $creator2, 'rina' => $rina2, 'version' => $version2] = personalFixture([3 => ['user' => 'creator', 'allow_creator' => true]]);
        signed($creator2, $version2);
        signed($rina2, $version2);
        signed($creator2, $version2);
    });
});

describe('replacing a signer', function () {
    it('is done by a company administrator with a reason, is audited, and moves the task and the right to sign', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'document' => $document, 'version' => $version] = personalFixture();
        signed($creator, $version);
        $url = route('documents.versions.signers.reassign', [$document->uuid, $version->uuid, 2]);

        $this->actingAs($creator)->post($url, ['user_id' => $colleague->id])->assertSessionHasErrors('reason');
        $this->actingAs($creator)->post($url, ['user_id' => $colleague->id, 'reason' => 'Rina cuti panjang'])->assertSessionHasNoErrors();

        expect(VersionStep::query()->where('version_id', $version->id)->where('position', 2)->value('assigned_user_id'))->toBe($colleague->id);

        $log = AuditLog::query()->where('event', 'dcs.signer.reassigned')->latest('id')->firstOrFail();
        expect($log->actor_id)->toBe($creator->id)
            ->and($log->tenant_id)->toBe($document->tenant_id)
            ->and($log->before['user_id'])->toBe($rina->id)
            ->and($log->after['user_id'])->toBe($colleague->id)
            ->and($log->after['reason'])->toBe('Rina cuti panjang');
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::SignerReassigned)->count())->toBe(1);
        expect(collect($this->notifier->sent)->last()['recipients'])->toBe([$colleague->id]);

        $queue = app(TaskQueue::class);
        expect($queue->counts($colleague)['sign'])->toBe(1)->and($queue->counts($rina)['sign'])->toBe(0);
        refused($rina, $version);
        signed($colleague, $version);
    });

    it('is refused for a column already signed, for the same person, an inactive user and another company', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'document' => $document, 'version' => $version] = personalFixture();
        signed($creator, $version);
        $reassign = fn (int $order, int $userId) => $this->actingAs($creator)->post(route('documents.versions.signers.reassign', [$document->uuid, $version->uuid, $order]), ['user_id' => $userId, 'reason' => 'Penggantian uji']);
        $assigned = fn (int $order) => VersionStep::query()->where('version_id', $version->id)->where('position', $order)->value('assigned_user_id');

        $reassign(1, $colleague->id);
        expect($assigned(1))->toBe($creator->id);

        $reassign(2, $rina->id);
        expect($assigned(2))->toBe($rina->id);

        $inactive = User::factory()->for($document->tenant)->role(SystemRole::Legal)->create(['is_active' => false]);
        $reassign(2, $inactive->id);
        expect($assigned(2))->toBe($rina->id);

        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();
        $reassign(2, $foreign->id);
        expect($assigned(2))->toBe($rina->id)->and(AuditLog::query()->where('event', 'dcs.signer.reassigned')->count())->toBe(0);
    });

    it('is not for a signer, a colleague or another company', function () {
        ['rina' => $rina, 'colleague' => $colleague, 'document' => $document, 'version' => $version] = personalFixture();
        $url = route('documents.versions.signers.reassign', [$document->uuid, $version->uuid, 3]);
        $body = ['user_id' => $colleague->id, 'reason' => 'Tidak berwenang'];

        $this->actingAs($rina)->post($url, $body)->assertForbidden();
        $this->actingAs($colleague)->post($url, $body)->assertForbidden();

        $outsider = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $this->actingAs($outsider)->post($url, $body)->assertNotFound();

        expect(AuditLog::query()->where('event', 'dcs.signer.reassigned')->count())->toBe(0);
    });
});

describe('document type template', function () {
    it('stores a named person for a column, and refuses an inactive user or one from another company', function () {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
        $rina = User::factory()->for($tenant)->role(SystemRole::Legal)->create();
        $gone = User::factory()->for($tenant)->role(SystemRole::Legal)->create(['is_active' => false]);
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();
        $this->actingAs($admin)->get(route('documents.config.types.index'))->assertOk();
        $type = fn (string $code, ?User $person) => ['code' => $code, 'name' => $code, 'layoutStyle' => 'standard', 'headerStyle' => 'iso', 'signatureGroups' => [['columns' => [
            ['label' => 'Disetujui oleh', 'role' => null, 'fillMode' => 'person', 'defaultUserId' => $person?->apiId(), 'allowCreator' => false],
        ]]]];

        $this->actingAs($admin)->post(route('documents.config.types.store'), $type('OKE', $rina))->assertSessionHasNoErrors();
        $step = $tenant->dcsDocumentTypes()->where('code', 'OKE')->firstOrFail()->steps()->firstOrFail();
        expect($step->fill_mode)->toBe('person')->and($step->default_user_id)->toBe($rina->id);

        foreach (['GONE' => $gone, 'FRGN' => $foreign, 'NONE' => null] as $code => $person) {
            $this->actingAs($admin)->post(route('documents.config.types.store'), $type($code, $person))->assertSessionHasErrors('signatureGroups');
            expect($tenant->dcsDocumentTypes()->where('code', $code)->exists())->toBeFalse();
        }
    });

    it('leaves every existing column legacy, with nobody picked', function () {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
        $this->actingAs($admin)->get(route('documents.config.types.index'))->assertOk();

        $steps = $tenant->dcsDocumentTypes()->where('code', 'SOP')->firstOrFail()->steps;

        expect($steps)->not->toBeEmpty()
            ->and($steps->pluck('fill_mode')->unique()->all())->toBe(['legacy'])
            ->and($steps->pluck('default_user_id')->filter()->all())->toBe([]);
    });
});
