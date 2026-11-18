<?php

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;

function versionFixture(): array
{
    $admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($admin->tenant)->create();
    DocumentVersion::factory()->for($document)->create(['major' => 1, 'minor' => 0, 'status' => VersionStatus::Effective]);

    return [$admin, $document];
}

it('adds a revision to the newest version and answers with the raw column names', function () {
    [$admin, $document] = versionFixture();

    $response = dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", ['bump' => 'revisi', 'changeSummary' => 'Perbaikan typo'])->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and(array_keys($response->json('version')))->toBe(['id', 'major_version', 'minor_version'])
        ->and([$response->json('version.major_version'), $response->json('version.minor_version')])->toBe([1, 1]);
    $version = DocumentVersion::query()->where('uuid', $response->json('version.id'))->sole();
    expect($version->status)->toBe(VersionStatus::Draft)->and($version->change_summary)->toBe('Perbaikan typo')->and($version->created_by_id)->toBe($admin->id);
});

it('treats anything but terbitan as revisi, and terbitan resets the revision', function () {
    [$admin, $document] = versionFixture();
    $client = fn (array $payload) => dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", $payload)->json('version');

    $a = $client([]);
    $b = $client(['bump' => 'whatever']);
    $c = $client(['bump' => 'terbitan']);
    $d = $client(['bump' => 'revisi']);

    expect(array_map(fn (array $v) => "{$v['major_version']}.{$v['minor_version']}", [$a, $b, $c, $d]))->toBe(['1.1', '1.2', '2.0', '2.1']);
});

it('bases the next number on the newest version, not on the effective one', function () {
    [$admin, $document] = versionFixture();
    DocumentVersion::factory()->for($document)->create(['major' => 4, 'minor' => 3, 'status' => VersionStatus::Draft]);

    $version = dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", ['bump' => 'terbitan'])->json('version');

    expect([$version['major_version'], $version['minor_version']])->toBe([5, 0]);
});

it('accepts a typed Terbitan and Revisi only when both are valid', function () {
    [$admin, $document] = versionFixture();
    $post = fn (array $payload) => dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", $payload)->json('version');

    $typed = $post(['major' => 7, 'minor' => 2]);
    $onlyMajor = $post(['major' => 9]);

    expect([$typed['major_version'], $typed['minor_version']])->toBe([7, 2])
        ->and([$onlyMajor['major_version'], $onlyMajor['minor_version']])->toBe([7, 3]);
});

it('answers 409 when the Terbitan and Revisi are already used', function () {
    [$admin, $document] = versionFixture();

    dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", ['major' => 1, 'minor' => 0])
        ->assertStatus(409)->assertExactJson(['error' => 'Terbitan 1 Revisi 0 sudah dipakai untuk dokumen ini. Pilih nomor lain.']);

    expect($document->versions()->count())->toBe(1);
});

it('records the history of the new version', function () {
    [$admin, $document] = versionFixture();

    $id = dcsAs($admin)->postJson("/api/dcs/documents/{$document->uuid}/versions", ['bump' => 'terbitan', 'changeSummary' => 'Besar'])->json('version.id');

    $event = DocumentEvent::query()->where('event_type', DocumentEventType::VersionCreated)->sole();
    expect($event->version_id)->toBe(DocumentVersion::query()->where('uuid', $id)->value('id'))
        ->and($event->actor_id)->toBe($admin->id)
        ->and($event->to_status)->toBe(VersionStatus::Draft)
        ->and($event->payload)->toEqual(['major' => 2, 'minor' => 0, 'bump' => 'terbitan', 'change_summary' => 'Besar']);
});

it('answers 404 for a document of another tenant', function () {
    [$admin] = versionFixture();
    $foreign = Document::factory()->create();

    dcsAs($admin)->postJson("/api/dcs/documents/{$foreign->uuid}/versions", [])->assertNotFound()->assertExactJson(['error' => 'Dokumen tidak ditemukan']);
});

it('forbids users without dcs.edit', function () {
    [, $document] = versionFixture();

    dcsAs(User::factory()->for($document->tenant)->role(SystemRole::Viewer)->create())->postJson("/api/dcs/documents/{$document->uuid}/versions", [])->assertForbidden();
});
