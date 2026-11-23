<?php

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ExternalReviewApproval;
use App\Models\Dcs\ReviewComment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;
use Inertia\Support\SessionKey;
use Tests\Support\RecordingNotifier;

/*
 * The rules of the outside review link and of what a guest does with it, through the screens
 * (these used to be checked only through the legacy Node-style API).
 */

beforeEach(function () {
    $this->notifier = new RecordingNotifier;
    app()->instance(DcsNotifier::class, $this->notifier);
    $this->owner = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($this->owner->tenant)->create(['owner_user_id' => $this->owner->id, 'created_by_id' => $this->owner->id, 'title' => 'Prosedur Cuti']);
    $this->version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::Draft, 'created_by_id' => $this->owner->id]);
});

/** Opens a link through the screen and returns the plain token from its address. */
function openLink(array $payload = []): string
{
    test()->actingAs(test()->owner)->post(route('documents.versions.external-review.enable', [test()->version->document, test()->version]), $payload);

    return basename(parse_url(session()->get(SessionKey::FLASH_DATA)['external_review_url'], PHP_URL_PATH));
}

it('replaces an open link, so the old token stops working', function () {
    $old = openLink();
    $new = openLink();

    auth()->logout();
    $this->get(route('external-review.show', $old))->assertNotFound();
    $this->get(route('external-review.show', $new))->assertOk();
    expect(ExternalReview::query()->count())->toBe(2)->and(ExternalReview::query()->whereNull('disabled_at')->count())->toBe(1);
});

it('has no expiry when no number of days is given', function () {
    openLink();

    expect(ExternalReview::query()->sole()->expires_at)->toBeNull();
});

it('unlocks only a version that has a live link', function () {
    $this->actingAs($this->owner)->post(route('documents.versions.external-review.unlock', [$this->version->document, $this->version]))->assertRedirect();

    expect(session()->get(SessionKey::FLASH_DATA)['toast'])->toBe(['type' => 'error', 'message' => 'Belum ada link review eksternal aktif untuk dokumen ini.']);
});

it('records a guest comment with a clean anchor and tells the owner', function () {
    $token = openLink();
    auth()->logout();

    $this->post(route('external-review.comment', $token), [
        'name' => '  Auditor Budi  ', 'body' => 'Bagian ini kurang jelas', 'anchor' => ['field' => 'section', 'sectionIndex' => 0, 'quote' => 'x', 'script' => '<b>'],
    ])->assertRedirect();

    $comment = ReviewComment::query()->sole();
    expect($comment->author_name)->toBe('Auditor Budi')->and($comment->author_role_key)->toBe('Pihak Eksternal')->and($comment->author_user_id)->toBeNull()
        ->and($comment->anchor)->toEqual(['field' => 'section', 'sectionIndex' => 0, 'quote' => 'x'])
        ->and($this->notifier->sent[0])->toMatchArray(['recipients' => [$this->owner->id], 'type' => 'warning']);
    $event = $this->version->document->events()->where('event_type', DocumentEventType::CommentAdded)->sole();
    expect($event->actor_type)->toBe(ActorType::Guest)->and($event->actor_name)->toBe('Auditor Budi');
});

it('records opening the link, and closing it keeps what was said and is idempotent', function () {
    $token = openLink();
    auth()->logout();
    $this->post(route('external-review.comment', $token), ['name' => 'Auditor', 'body' => 'Catatan']);

    $close = fn () => $this->actingAs($this->owner)->post(route('documents.versions.external-review.disable', [$this->version->document, $this->version]))->assertRedirect();
    $close();
    $close();

    $events = $this->version->document->events();
    expect(ReviewComment::query()->count())->toBe(1)->and($events->where('event_type', DocumentEventType::ExternalReviewEnabled)->count())->toBe(1)
        ->and($this->version->document->events()->where('event_type', DocumentEventType::ExternalReviewDisabled)->count())->toBe(1);
});

it('records the sign-off with its note, tells the owner, and refuses more until the owner unlocks', function () {
    $token = openLink();
    auth()->logout();

    $this->post(route('external-review.approve', $token), ['name' => 'Auditor Budi', 'note' => 'Sesuai'])->assertRedirect();

    expect(ExternalReviewApproval::query()->sole()->only(['name', 'note']))->toBe(['name' => 'Auditor Budi', 'note' => 'Sesuai'])->and(ExternalReview::query()->sole()->locked)->toBeTrue()
        ->and($this->notifier->sent[0]['type'])->toBe('success')
        ->and($this->version->document->events()->where('event_type', DocumentEventType::ExternalReviewApproved)->count())->toBe(1);

    $this->post(route('external-review.comment', $token), ['name' => 'A', 'body' => 'B'])->assertRedirect();
    expect(session()->get(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error')->and(ReviewComment::query()->count())->toBe(0);
    $this->post(route('external-review.approve', $token), ['name' => 'A'])->assertRedirect();
    expect(session()->get(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error')->and(ExternalReviewApproval::query()->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('documents.versions.external-review.unlock', [$this->version->document, $this->version]))->assertRedirect();
    auth()->logout();
    $this->post(route('external-review.comment', $token), ['name' => 'A', 'body' => 'B'])->assertRedirect();

    expect(ReviewComment::query()->count())->toBe(1)->and($this->version->document->events()->where('event_type', DocumentEventType::ExternalReviewUnlocked)->count())->toBe(1);
});
