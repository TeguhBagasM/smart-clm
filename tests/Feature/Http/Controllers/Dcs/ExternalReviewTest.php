<?php

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ExternalReviewApproval;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;
use Tests\Support\RecordingNotifier;

beforeEach(function () {
    $this->notifier = new RecordingNotifier;
    app()->instance(DcsNotifier::class, $this->notifier);
    config(['dcs.share_base_url' => 'https://clm.example.test/']);
});

/**
 * @return array{owner: User, version: DocumentVersion}
 */
function externalFixture(VersionStatus $status = VersionStatus::Draft): array
{
    $owner = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($owner->tenant)->create(['owner_user_id' => $owner->id, 'created_by_id' => $owner->id, 'title' => 'Prosedur Cuti']);
    $version = DocumentVersion::factory()->for($document)->create(['status' => $status, 'created_by_id' => $owner->id]);
    VersionContent::query()->forceCreate(['version_id' => $version->id, 'purpose' => 'Tujuan', 'scope' => 'Lingkup', 'language_mode' => 'single', 'flow_mode' => 'none', 'flow_display_style' => 'diagram', 'flow_lanes' => [], 'flow_steps' => [], 'draft_revision' => 1]);
    VersionSection::query()->forceCreate(['version_id' => $version->id, 'position' => 1, 'heading' => 'Bab 1', 'content' => 'Isi bab']);

    return compact('owner', 'version');
}

function enableLink(User $owner, DocumentVersion $version, array $payload = []): string
{
    return dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/enable", $payload)->assertOk()->json('token');
}

describe('enable, disable and unlock', function () {
    it('opens a link, answers with the token and URL, and stores only a hash', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();

        $response = dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/enable", ['expiresInDays' => 7])->assertOk();

        expect($response->json('token'))->toHaveLength(48)->and($response->json('url'))->toBe("https://clm.example.test/?dcsReviewToken={$response->json('token')}")
            ->and($response->json('expiresAt'))->not->toBeNull();
        $review = ExternalReview::query()->sole();
        expect($review->token_hash)->toBe(hash('sha256', $response->json('token')))->and($review->token_hash)->not->toBe($response->json('token'))
            ->and($review->expires_at->isFuture())->toBeTrue();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ExternalReviewEnabled)->count())->toBe(1);
    });

    it('replaces an open link, so the old token stops working', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $old = enableLink($owner, $version);
        $new = enableLink($owner, $version);

        $this->getJson("/api/dcs-external-review/{$old}")->assertNotFound();
        $this->getJson("/api/dcs-external-review/{$new}")->assertOk();
        expect(ExternalReview::query()->count())->toBe(2)->and(ExternalReview::query()->whereNull('disabled_at')->count())->toBe(1);
    });

    it('has no expiry without a positive number of days', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();

        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/enable", ['expiresInDays' => 0])->assertOk()->assertJsonPath('expiresAt', null);
    });

    it('only works on a draft or a version under review, for dcs.edit holders of the tenant', function () {
        ['owner' => $owner, 'version' => $approved] = externalFixture(VersionStatus::Approved);
        $viewer = User::factory()->for($owner->tenant)->role(SystemRole::Viewer)->create();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        dcsAs($owner)->postJson("/api/dcs/versions/{$approved->uuid}/external-review/enable")->assertStatus(409);
        ['version' => $draft] = externalFixture();
        dcsAs($viewer)->postJson("/api/dcs/versions/{$draft->uuid}/external-review/enable")->assertForbidden();
        dcsAs($foreign)->postJson("/api/dcs/versions/{$draft->uuid}/external-review/enable")->assertNotFound();
        expect(ExternalReview::query()->count())->toBe(0);
    });

    it('closes the link and keeps what was said', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version);
        $this->postJson("/api/dcs-external-review/{$token}/comments", ['name' => 'Auditor', 'body' => 'Catatan'])->assertOk();

        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/disable")->assertOk()->assertExactJson(['success' => true]);
        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/disable")->assertOk();

        $this->getJson("/api/dcs-external-review/{$token}")->assertNotFound();
        expect(ReviewComment::query()->count())->toBe(1)->and(DocumentEvent::query()->where('event_type', DocumentEventType::ExternalReviewDisabled)->count())->toBe(1);
    });

    it('unlocks only a version that has a live link', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();

        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/unlock")->assertStatus(409)->assertJsonPath('error', 'Belum ada link review eksternal aktif untuk dokumen ini.');
    });
});

describe('public review', function () {
    it('shows the content and the comments without internal user ids', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version);
        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/comments", ['body' => 'Catatan internal'])->assertOk();

        $response = $this->getJson("/api/dcs-external-review/{$token}")->assertOk();

        expect($response->json('document'))->toMatchArray(['title' => 'Prosedur Cuti', 'major' => 1, 'minor' => 0, 'purpose' => 'Tujuan', 'scope' => 'Lingkup'])
            ->and($response->json('document.sections'))->toBe([['heading' => 'Bab 1', 'content' => 'Isi bab']])
            ->and($response->json('comments.0.body'))->toBe('Catatan internal')->and($response->json('comments.0'))->not->toHaveKey('authorUserId')
            ->and($response->json('locked'))->toBeFalse();
    });

    it('answers 404 for unknown, expired and malformed tokens', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version, ['expiresInDays' => 1]);
        ExternalReview::query()->update(['expires_at' => now()->subMinute()]);

        $this->getJson("/api/dcs-external-review/{$token}")->assertNotFound()->assertJsonPath('error', 'Link review tidak valid atau sudah kadaluarsa.');
        $this->getJson('/api/dcs-external-review/nonsense')->assertNotFound();
        $this->postJson('/api/dcs-external-review/nonsense/comments', ['name' => 'A', 'body' => 'B'])->assertNotFound();
        $this->postJson('/api/dcs-external-review/nonsense/approve', ['name' => 'A'])->assertNotFound();
    });

    it('records a guest comment with a clean anchor and tells the owner', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version);

        $response = $this->postJson("/api/dcs-external-review/{$token}/comments", [
            'name' => '  Auditor Budi  ', 'body' => 'Bagian ini kurang jelas', 'anchor' => ['field' => 'section', 'sectionIndex' => 0, 'quote' => 'x'],
        ])->assertOk();

        expect($response->json('comment'))->toMatchArray(['authorName' => 'Auditor Budi', 'authorRole' => 'Pihak Eksternal', 'status' => 'open'])
            ->and($response->json('comment.anchor'))->toBe(['field' => 'section', 'sectionIndex' => 0, 'quote' => 'x'])
            ->and($response->json('comment'))->not->toHaveKey('resolvedBy')->not->toHaveKey('authorUserId');
        expect(ReviewComment::query()->sole()->author_user_id)->toBeNull();
        expect($this->notifier->sent[0])->toMatchArray(['recipients' => [$owner->id], 'type' => 'warning']);
        $event = DocumentEvent::query()->where('event_type', DocumentEventType::CommentAdded)->sole();
        expect($event->actor_type)->toBe(ActorType::Guest)->and($event->actor_name)->toBe('Auditor Budi');
    });

    it('needs a name and a comment', function (array $payload) {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version);

        $this->postJson("/api/dcs-external-review/{$token}/comments", $payload)->assertStatus(400)->assertJsonPath('error', 'Nama dan komentar wajib diisi.');
        expect(ReviewComment::query()->count())->toBe(0);
    })->with([[[]], [['name' => 'A']], [['body' => 'B']], [['name' => '  ', 'body' => 'B']]]);

    it('locks after the sign-off until the owner unlocks it', function () {
        ['owner' => $owner, 'version' => $version] = externalFixture();
        $token = enableLink($owner, $version);

        $this->postJson("/api/dcs-external-review/{$token}/approve", ['name' => ''])->assertStatus(400)->assertJsonPath('error', 'Nama wajib diisi.');
        $this->postJson("/api/dcs-external-review/{$token}/approve", ['name' => 'Auditor Budi', 'note' => 'Sesuai'])->assertOk()->assertExactJson(['success' => true]);

        expect(ExternalReviewApproval::query()->sole()->only(['name', 'note']))->toBe(['name' => 'Auditor Budi', 'note' => 'Sesuai']);
        expect(ExternalReview::query()->sole()->locked)->toBeTrue()->and($this->getJson("/api/dcs-external-review/{$token}")->json('locked'))->toBeTrue();
        $this->postJson("/api/dcs-external-review/{$token}/comments", ['name' => 'A', 'body' => 'B'])->assertForbidden();
        $this->postJson("/api/dcs-external-review/{$token}/approve", ['name' => 'A'])->assertForbidden();
        expect($this->notifier->sent[0]['type'])->toBe('success');

        dcsAs($owner)->postJson("/api/dcs/versions/{$version->uuid}/external-review/unlock")->assertOk();
        $this->postJson("/api/dcs-external-review/{$token}/comments", ['name' => 'A', 'body' => 'B'])->assertOk();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ExternalReviewApproved)->sole()->actor_type)->toBe(ActorType::Guest);
    });
});
