<?php

use App\Actions\Dcs\CreateVersion;
use App\Actions\Dcs\SaveDraftContent;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\VersionAttachment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;

/*
 * The rules of CreateVersion, held at the Action (these used to be checked only through the
 * legacy Node-style API).
 */

beforeEach(function () {
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->document = Document::factory()->for($this->admin->tenant)->create();
    DocumentVersion::factory()->for($this->document)->create(['major' => 1, 'minor' => 0, 'status' => VersionStatus::Effective]);
});

it('bases the next number on the newest version, not on the one in force', function () {
    DocumentVersion::factory()->for($this->document)->create(['major' => 4, 'minor' => 3, 'status' => VersionStatus::Draft]);

    $version = app(CreateVersion::class)->handle($this->admin, $this->document, 'terbitan', null);
    $revision = app(CreateVersion::class)->handle($this->admin, $this->document, 'revisi', null);

    expect([$version->major, $version->minor])->toBe([5, 0])->and([$revision->major, $revision->minor])->toBe([5, 1]);
});

it('records the history of the new version', function () {
    $version = app(CreateVersion::class)->handle($this->admin, $this->document, 'terbitan', 'Besar');

    $event = DocumentEvent::query()->where('event_type', DocumentEventType::VersionCreated)->sole();

    expect($event->version_id)->toBe($version->id)->and($event->actor_id)->toBe($this->admin->id)->and($event->to_status)->toBe(VersionStatus::Draft)
        ->and($event->payload)->toEqual(['major' => 2, 'minor' => 0, 'bump' => 'terbitan', 'change_summary' => 'Besar']);
});

it('starts the new draft with the text of the newest version, for a revision and a new issue', function (string $bump) {
    $source = DocumentVersion::factory()->for($this->document)->create(['major' => 2, 'minor' => 0, 'status' => VersionStatus::Draft]);

    app(SaveDraftContent::class)->handle($this->admin, $this->admin->tenant, $source, [
        'purpose' => 'Tujuan awal',
        'sections' => [['heading' => 'Prosedur', 'content' => 'Langkah satu', 'table' => ['columns' => ['A'], 'rows' => [['1']]]]],
        'appendix' => [['label' => 'Formulir', 'url' => 'https://example.test/form']],
        'flowMode' => 'builder',
        'flowLanes' => ['Staf'],
        'flowSteps' => [['id' => 's1', 'type' => 'start', 'text' => 'Mulai', 'actor' => 'Staf', 'row' => 0]],
    ]);
    $source->forceFill(['status' => VersionStatus::Approved])->save();
    VersionStep::query()->where('version_id', $source->id)->update(['status' => 'signed']);

    $new = app(CreateVersion::class)->handle($this->admin, $this->document, $bump, null);
    $copy = VersionContent::query()->where('version_id', $new->id)->sole();

    expect($copy->purpose)->toBe('Tujuan awal')->and($copy->flow_mode)->toBe('builder')->and($copy->flow_steps[0]['text'])->toBe('Mulai')->and($copy->draft_revision)->toBe(1)
        ->and(VersionSection::query()->where('version_id', $new->id)->sole()->only(['heading', 'content', 'table_data']))->toEqual(['heading' => 'Prosedur', 'content' => 'Langkah satu', 'table_data' => ['columns' => ['A'], 'rows' => [['1']]]])
        ->and(VersionAttachment::query()->where('version_id', $new->id)->sole()->label)->toBe('Formulir')
        ->and(VersionStep::query()->where('version_id', $new->id)->pluck('status')->unique()->all())->toBe(['pending'])
        ->and(Rendition::query()->where('version_id', $new->id)->exists())->toBeFalse()
        ->and(VersionSection::query()->where('version_id', $source->id)->count())->toBe(1);
})->with(['revisi', 'terbitan']);

it('leaves the new draft empty when the newest version has no written content', function () {
    $new = app(CreateVersion::class)->handle($this->admin, $this->document, 'revisi', null);

    expect(VersionContent::query()->where('version_id', $new->id)->exists())->toBeFalse();
});
