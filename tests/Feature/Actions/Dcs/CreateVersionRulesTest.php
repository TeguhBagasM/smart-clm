<?php

use App\Actions\Dcs\CreateVersion;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
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
