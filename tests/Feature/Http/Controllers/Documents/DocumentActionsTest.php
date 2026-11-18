<?php

use App\Actions\Dcs\StoreRendition;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsNotifier::class, new RecordingNotifier);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
});

/**
 * A version of the test document with a stored PDF and a two-column signing sheet (Legal, then Admin).
 */
function actVersion(VersionStatus $status, array $attributes = []): DocumentVersion
{
    $version = DocumentVersion::factory()->for(test()->document)->create(['major' => test()->document->versions()->count() + 1, 'status' => $status, 'created_by_id' => test()->admin->id, ...$attributes]);
    app(StoreRendition::class)->handle(test()->admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());

    foreach ([['Dibuat oleh', SystemRole::Legal], ['Disetujui oleh', SystemRole::Admin]] as $position => [$label, $role]) {
        VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'position' => $position + 1, 'label' => $label, 'role_id' => systemRoleId($role), 'status' => 'pending']);
    }

    return $version;
}

function actUrl(string $name, DocumentVersion $version, array $extra = []): string
{
    return route("documents.versions.{$name}", [test()->document, $version, ...$extra]);
}

function actPost(User $user, string $name, DocumentVersion $version, array $payload = [], array $extra = [])
{
    return test()->actingAs($user)->from(route('documents.show', test()->document))->post(actUrl($name, $version, $extra), $payload);
}

/**
 * The toast flashed by the last action, as ['type' => ..., 'message' => ...].
 */
function actToast($response): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

function actCan(User $user, DocumentVersion $version): array
{
    $can = [];
    test()->actingAs($user)->get(route('documents.show', [test()->document, 'version' => $version->uuid]))->assertInertia(function (Assert $page) use (&$can) {
        $can = $page->toArray()['props']['version']['can'];
    });

    return $can;
}

describe('abilities shown on the page', function () {
    it('lets anyone submit a draft and comment, but only Document Control release and distribute', function () {
        $draft = actVersion(VersionStatus::Draft);

        expect(actCan($this->viewer, $draft))->toMatchArray(['submit' => true, 'comment' => true, 'sign' => false, 'request_changes' => false, 'release' => false, 'withdraw' => false, 'distribute' => false, 'acknowledge' => true, 'resolve_comment' => false]);
        expect(actCan($this->admin, $draft))->toMatchArray(['distribute' => true, 'resolve_comment' => true, 'release' => false]);
    });

    it('offers signing only to whoever holds the column whose turn it is', function () {
        $review = actVersion(VersionStatus::UnderReview);

        expect(actCan($this->legal, $review))->toMatchArray(['sign' => true, 'request_changes' => true, 'submit' => false]);
        expect(actCan($this->admin, $review))->toMatchArray(['sign' => false, 'request_changes' => false, 'return_to_draft' => true]);
        expect(actCan($this->viewer, $review))->toMatchArray(['sign' => false, 'comment' => true, 'return_to_draft' => false]);
    });

    it('offers release only for an approved version and withdraw only for the one in force', function () {
        $approved = actVersion(VersionStatus::Approved);
        $effective = actVersion(VersionStatus::Effective);

        expect(actCan($this->admin, $approved))->toMatchArray(['release' => true, 'cancel_release' => false, 'withdraw' => false, 'comment' => false]);
        $approved->forceFill(['scheduled_effective_at' => now()->addWeek()])->save();
        expect(actCan($this->admin, $approved))->toMatchArray(['cancel_release' => true]);
        expect(actCan($this->admin, $effective))->toMatchArray(['withdraw' => true, 'release' => false]);
    });

    it('stops offering the read confirmation once given', function () {
        $effective = actVersion(VersionStatus::Effective);
        expect(actCan($this->legal, $effective)['acknowledge'])->toBeTrue();

        actPost($this->legal, 'acknowledge', $effective)->assertRedirect();

        expect(actCan($this->legal, $effective)['acknowledge'])->toBeFalse();
    });
});

describe('signing flow', function () {
    it('submits, signs both columns and approves, with a toast each time', function () {
        $version = actVersion(VersionStatus::Draft);

        actPost($this->viewer, 'submit', $version)->assertRedirect(route('documents.show', $this->document))->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dokumen diajukan untuk tanda tangan.']);
        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview);

        actPost($this->legal, 'sign', $version)->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Tanda tangan tercatat.']);
        actPost($this->admin, 'sign', $version);

        expect($version->fresh()->status)->toBe(VersionStatus::Approved)->and(Approval::query()->whereNull('voided_at')->count())->toBe(2);
        expect($this->engine->stamped)->toHaveCount(2);
    });

    it('explains a refusal in a toast instead of an error page, and changes nothing', function () {
        $version = actVersion(VersionStatus::UnderReview);

        $toast = actToast(actPost($this->admin, 'sign', $version)->assertRedirect());
        expect($toast['type'] === 'error' && str_contains($toast['message'], 'memerlukan role'))->toBeTrue();
        $toast = actToast(actPost($this->legal, 'submit', $version));
        expect($toast['type'] === 'error')->toBeTrue();
        expect(Approval::query()->count())->toBe(0)->and($version->fresh()->status)->toBe(VersionStatus::UnderReview);
    });

    it('hides engine failures behind the action\'s message', function () {
        $version = actVersion(VersionStatus::UnderReview);
        $this->engine->fail = true;

        $toast = actToast(actPost($this->legal, 'sign', $version));
        expect($toast['type'] === 'error' && $toast['message'] === 'Gagal menandatangani & menyetujui versi.')->toBeTrue();
    });

    it('sends the version back with a note, and needs a note', function () {
        $version = actVersion(VersionStatus::UnderReview);

        actPost($this->legal, 'request-changes', $version, ['body' => ''])->assertSessionHasErrors('body');
        actPost($this->legal, 'request-changes', $version, ['body' => 'Perbaiki bab 2', 'anchor' => ['field' => 'scope']])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dokumen dikembalikan ke penyusun untuk direvisi.']);

        expect($version->fresh()->status)->toBe(VersionStatus::Draft);
        expect(ReviewComment::query()->sole()->only(['body', 'anchor']))->toBe(['body' => 'Perbaiki bab 2', 'anchor' => ['field' => 'scope']]);
    });

    it('lets the owner pull a version back, but not an outsider', function () {
        $version = actVersion(VersionStatus::UnderReview);

        $toast = actToast(actPost($this->viewer, 'return-to-draft', $version));
        expect($toast['type'] === 'error')->toBeTrue();
        actPost($this->admin, 'return-to-draft', $version)->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dokumen ditarik dari antrean tanda tangan.']);
        expect($version->fresh()->status)->toBe(VersionStatus::Draft);
    });
});

describe('comments', function () {
    it('adds a comment and lets the owner resolve it', function () {
        $version = actVersion(VersionStatus::Draft);

        actPost($this->legal, 'comments.store', $version, ['body' => '  Typo di tujuan ', 'anchor' => ['field' => 'purpose', 'quote' => 'abc']])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Komentar ditambahkan.']);
        $comment = ReviewComment::query()->sole();
        expect($comment->body)->toBe('Typo di tujuan')->and($comment->anchor)->toBe(['field' => 'purpose', 'quote' => 'abc']);

        $toast = actToast(actPost($this->legal, 'comments.resolve', $version, extra: ['comment' => $comment->uuid]));
        expect($toast['type'] === 'error')->toBeTrue();
        actPost($this->admin, 'comments.resolve', $version, extra: ['comment' => $comment->uuid])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Komentar ditandai selesai.']);
        expect($comment->fresh()->status->value)->toBe('resolved');
    });

    it('needs a body and a version that is still being reviewed', function () {
        actPost($this->legal, 'comments.store', actVersion(VersionStatus::Draft), ['body' => ''])->assertSessionHasErrors('body');
        $toast = actToast(actPost($this->legal, 'comments.store', actVersion(VersionStatus::Effective), ['body' => 'x']));
        expect($toast['type'] === 'error')->toBeTrue();
        expect(ReviewComment::query()->count())->toBe(0);
    });
});

describe('release and distribution', function () {
    it('releases now, or schedules when the date is ahead, and cancels the schedule', function () {
        $first = actVersion(VersionStatus::Approved);
        actPost($this->admin, 'release', $first, ['effective_at' => ''])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Versi diberlakukan.']);
        expect($first->fresh()->status)->toBe(VersionStatus::Effective)->and($this->document->fresh()->current_version_id)->toBe($first->id);

        $second = actVersion(VersionStatus::Approved);
        $toast = actToast(actPost($this->admin, 'release', $second, ['effective_at' => now()->addDays(5)->toDateString()]));
        expect($toast['type'] === 'success' && str_starts_with($toast['message'], 'Rilis dijadwalkan pada'))->toBeTrue();
        expect($second->fresh()->status)->toBe(VersionStatus::Approved)->and($second->fresh()->scheduled_effective_at)->not->toBeNull();

        actPost($this->admin, 'cancel-release', $second)->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Jadwal rilis dibatalkan.']);
        expect($second->fresh()->scheduled_effective_at)->toBeNull();
        actPost($this->admin, 'release', $second, ['effective_at' => 'bukan tanggal'])->assertSessionHasErrors('effective_at');
    });

    it('withdraws the version in force', function () {
        $effective = actVersion(VersionStatus::Effective);
        $this->document->forceFill(['current_version_id' => $effective->id])->save();

        actPost($this->admin, 'withdraw', $effective)->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dokumen ditarik dan tidak lagi berlaku.']);

        expect($effective->fresh()->status)->toBe(VersionStatus::Superseded)->and($this->document->fresh()->current_version_id)->toBeNull();
    });

    it('keeps release, withdraw and distribute for Document Control', function (string $action, VersionStatus $status, array $payload) {
        $version = actVersion($status);

        actPost($this->viewer, $action, $version, $payload)->assertForbidden();
        expect($version->fresh()->status)->toBe($status);
    })->with([
        'release' => ['release', VersionStatus::Approved, []],
        'cancel' => ['cancel-release', VersionStatus::Approved, []],
        'withdraw' => ['withdraw', VersionStatus::Effective, []],
        'distribute' => ['distribute', VersionStatus::Effective, ['user_ids' => [1]]],
    ]);

    it('distributes to users of the company only, creating pending receipts', function () {
        $version = actVersion(VersionStatus::Effective);
        $foreign = User::factory()->for(Tenant::factory()->create())->create();

        actPost($this->admin, 'distribute', $version, ['user_ids' => []])->assertSessionHasErrors('user_ids');
        actPost($this->admin, 'distribute', $version, ['user_ids' => [$foreign->id]])->assertSessionHasErrors('user_ids.0');
        actPost($this->admin, 'distribute', $version, ['user_ids' => [$this->legal->id, $this->viewer->id, $this->legal->id], 'mandatory' => false])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dokumen didistribusikan ke 2 penerima.']);

        expect(ReadReceipt::query()->pluck('user_id')->sort()->values()->all())->toBe([$this->legal->id, $this->viewer->id])
            ->and(ReadReceipt::query()->pluck('status')->unique()->all())->toBe([ReceiptStatus::Pending]);
    });

    it('offers recipient choices only when asked for', function () {
        $version = actVersion(VersionStatus::Effective);

        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page->missing('recipient_options'));
        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('recipient_options', fn (Assert $reloaded) => $reloaded->has('recipient_options', 3)->where('recipient_options.0', fn ($person) => collect($person)->keys()->all() === ['id', 'name', 'role'])));
        expect($version->id)->not->toBeNull();
    });
});

describe('isolation', function () {
    it('hides other companies\' documents and versions of other documents', function () {
        $version = actVersion(VersionStatus::Draft);
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $otherDocument = Document::factory()->for($this->tenant)->create();
        $otherVersion = DocumentVersion::factory()->for($otherDocument)->create();

        actPost($foreign, 'submit', $version)->assertNotFound();
        actPost($foreign, 'comments.store', $version, ['body' => 'x'])->assertNotFound();
        $this->actingAs($this->admin)->post(route('documents.versions.submit', [$this->document, $otherVersion]))->assertNotFound();
        expect($version->fresh()->status)->toBe(VersionStatus::Draft);
    });

    it('redirects guests to the login page', function () {
        $version = actVersion(VersionStatus::Draft);

        $this->post(actUrl('submit', $version))->assertRedirect(route('login'));
    });
});
