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

function signAs(User $user, DocumentVersion $version)
{
    return dcsAs($user)->postJson("/api/dcs/versions/{$version->uuid}/sign-approve");
}

describe('only the named person signs', function () {
    it('refuses a colleague with the same role and department, and lets the named person sign', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'version' => $version] = personalFixture();

        signAs($creator, $version)->assertOk();
        signAs($colleague, $version)->assertForbidden();
        expect(Approval::query()->where('version_id', $version->id)->count())->toBe(1);

        signAs($rina, $version)->assertOk();
        expect(Approval::query()->where('version_id', $version->id)->count())->toBe(2);
    });

    it('closes a new-style column that has nobody yet, even to holders of its role', function () {
        ['creator' => $creator, 'rina' => $rina, 'version' => $version] = personalFixture([2 => ['user' => null]]);

        signAs($creator, $version)->assertOk();
        signAs($rina, $version)->assertForbidden();
    });

    it('keeps the old rule for a legacy column: anyone holding its role may sign', function () {
        ['creator' => $creator, 'colleague' => $colleague, 'version' => $version] = personalFixture([2 => ['mode' => 'legacy', 'user' => null]]);

        signAs($creator, $version)->assertOk();
        signAs($colleague, $version)->assertOk();
    });

    it('gives the task only to the named person', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'version' => $version] = personalFixture();
        signAs($creator, $version)->assertOk();

        $queue = app(TaskQueue::class);

        expect($queue->counts($rina)['sign'] ?? null)->toBe(1)
            ->and($queue->counts($colleague)['sign'] ?? null)->toBe(0);
    });
});

describe('the creator', function () {
    it('cannot approve their own document after the first column, unless the column allows it', function () {
        ['creator' => $creator, 'rina' => $rina, 'version' => $version] = personalFixture([3 => ['user' => 'creator']]);

        signAs($creator, $version)->assertOk();
        signAs($rina, $version)->assertOk();
        signAs($creator, $version)->assertForbidden()->assertJsonPath('error', 'Pembuat dokumen tidak boleh menyetujui dokumennya sendiri.');

        ['creator' => $creator2, 'rina' => $rina2, 'version' => $version2] = personalFixture([3 => ['user' => 'creator', 'allow_creator' => true]]);
        signAs($creator2, $version2)->assertOk();
        signAs($rina2, $version2)->assertOk();
        signAs($creator2, $version2)->assertOk();
    });
});

describe('replacing a signer', function () {
    it('is done by a company administrator with a reason, is audited, and moves the task and the right to sign', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'document' => $document, 'version' => $version] = personalFixture();
        signAs($creator, $version)->assertOk();
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
        signAs($rina, $version)->assertForbidden();
        signAs($colleague, $version)->assertOk();
    });

    it('is refused for a column already signed, for the same person, an inactive user and another company', function () {
        ['creator' => $creator, 'rina' => $rina, 'colleague' => $colleague, 'document' => $document, 'version' => $version] = personalFixture();
        signAs($creator, $version)->assertOk();
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
