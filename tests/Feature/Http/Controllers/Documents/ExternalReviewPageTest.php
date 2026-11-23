<?php

use App\Actions\Dcs\EnableExternalReview;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ReviewComment;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
    $this->other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti']);
    $this->document = Document::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    $this->version = $this->document->versions()->first();
    auth()->logout();
});

function ownerUrl(string $name): string
{
    return route("documents.versions.external-review.{$name}", [test()->document, test()->version]);
}

function reviewToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

/** A live link, as the plain token the outside party holds. */
function liveToken(?int $days = 7): string
{
    return app(EnableExternalReview::class)->handle(test()->admin, test()->version, $days)['token'];
}

describe('owner side', function () {
    it('is refused to guests, people without dcs.edit and other companies', function () {
        foreach (['enable', 'disable', 'unlock'] as $name) {
            $url = $name === 'enable' ? route('documents.versions.external-review.enable', [$this->document, $this->version]) : ownerUrl($name);
            auth()->logout();
            $this->post($url)->assertRedirect(route('login'));
            $this->actingAs($this->viewer)->post($url)->assertForbidden();
            $this->actingAs($this->other)->post($url)->assertNotFound();
        }
        expect(ExternalReview::query()->count())->toBe(0);
    });

    it('validates the number of days', function () {
        $this->actingAs($this->admin)->post(ownerUrl('enable'), ['expires_in_days' => 0])->assertSessionHasErrors('expires_in_days');
        $this->actingAs($this->admin)->post(ownerUrl('enable'), ['expires_in_days' => 400])->assertSessionHasErrors('expires_in_days');
        $this->actingAs($this->admin)->post(ownerUrl('enable'), ['expires_in_days' => 'abc'])->assertSessionHasErrors('expires_in_days');
    });

    it('opens a link, shows its address once and stores only the hash', function () {
        $this->actingAs($this->admin)->post(ownerUrl('enable'), ['expires_in_days' => 7])->assertRedirect();

        $url = session()->get(SessionKey::FLASH_DATA)['external_review_url'];
        $token = basename(parse_url($url, PHP_URL_PATH));
        $review = ExternalReview::query()->sole();

        expect($url)->toContain('/tinjau/dcs/')->and($review->token_hash)->toBe(hash('sha256', $token))->and($review->token_hash)->not->toBe($token)
            ->and($review->expires_at->isFuture())->toBeTrue()->and(reviewToast()['type'])->toBe('success');
    });

    it('starts the link with the public address when one is configured', function () {
        config(['dcs.share_base_url' => 'https://tinjau.example.com/']);

        $this->actingAs($this->admin)->post(ownerUrl('enable'));

        expect(session()->get(SessionKey::FLASH_DATA)['external_review_url'])->toStartWith('https://tinjau.example.com/tinjau/dcs/');
    });

    it('explains when the version no longer takes a link, and offers the button only when it does', function () {
        $can = fn () => $this->actingAs($this->admin)->get(route('documents.show', $this->document))->viewData('page')['props']['version']['can']['start_external_review'];
        expect($can())->toBeTrue();

        $this->version->forceFill(['status' => VersionStatus::Approved])->save();
        expect($can())->toBeFalse();

        $this->actingAs($this->admin)->post(ownerUrl('enable'))->assertRedirect();
        expect(reviewToast()['type'])->toBe('error')->and(ExternalReview::query()->count())->toBe(0);
    });

    it('closes the link and unlocks it after sign-off', function () {
        $token = liveToken();

        $this->get(route('external-review.show', $token))->assertOk();
        $this->actingAs($this->admin)->post(ownerUrl('disable'))->assertRedirect();
        auth()->logout();
        $this->get(route('external-review.show', $token))->assertNotFound();

        $token = liveToken();
        $this->post(route('external-review.approve', $token), ['name' => 'Budi'])->assertRedirect();
        expect(ExternalReview::resolve($token)->locked)->toBeTrue();
        $this->actingAs($this->admin)->post(ownerUrl('unlock'))->assertRedirect();
        expect(ExternalReview::resolve($token)->locked)->toBeFalse();
    });
});

describe('public page', function () {
    it('shows the document without any login or internal id', function () {
        $token = liveToken();

        $this->get(route('external-review.show', $token))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/ExternalReview')
            ->where('token', $token)
            ->where('review.document.title', 'Prosedur Cuti')
            ->where('review.locked', false)
            ->missing('review.document.owner')
            ->where('auth.user', null));
        $this->get(route('external-review.show', $token))->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    });

    it('answers the same friendly 404 for an unknown, closed or expired link', function () {
        $expired = liveToken(1);
        ExternalReview::query()->update(['expires_at' => now()->subMinute()]);

        foreach (['x'.str_repeat('a', 40), $expired] as $token) {
            $this->get(route('external-review.show', $token))->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('public/ExternalReview')->where('review', null));
        }
    });

    it('takes a comment from an outside party and records it as a guest', function () {
        $token = liveToken();

        $this->post(route('external-review.comment', $token), ['name' => 'Siti (Auditor)', 'body' => 'Langkah 2 kurang jelas.', 'anchor' => ['field' => 'purpose']])->assertRedirect();

        $comment = ReviewComment::query()->sole();
        expect($comment->author_user_id)->toBeNull()->and($comment->author_name)->toBe('Siti (Auditor)')->and($comment->anchor['field'])->toBe('purpose')->and(reviewToast()['type'])->toBe('success');
        $this->assertDatabaseHas('dcs_document_events', ['version_id' => $this->version->id, 'event_type' => 'comment.added', 'actor_type' => 'guest']);
    });

    it('asks for a name and a comment', function () {
        $token = liveToken();

        $this->post(route('external-review.comment', $token), ['name' => '', 'body' => ''])->assertSessionHasErrors(['name', 'body']);
        $this->post(route('external-review.comment', $token), ['name' => str_repeat('a', 81), 'body' => 'x'])->assertSessionHasErrors('name');
        $this->post(route('external-review.approve', $token), ['name' => ''])->assertSessionHasErrors('name');
        expect(ReviewComment::query()->count())->toBe(0);
    });

    it('locks after sign-off and refuses further comments with the reason', function () {
        $token = liveToken();

        $this->post(route('external-review.approve', $token), ['name' => 'Budi', 'note' => 'Sesuai'])->assertRedirect();
        $this->post(route('external-review.comment', $token), ['name' => 'Budi', 'body' => 'Satu lagi'])->assertRedirect();

        expect(reviewToast()['type'])->toBe('error')->and(ReviewComment::query()->count())->toBe(0);
        $this->get(route('external-review.show', $token))->assertInertia(fn (Assert $page) => $page->where('review.locked', true));
    });

    it('sends writes to a dead link back to the 404 page without storing anything', function () {
        $token = liveToken();
        ExternalReview::query()->update(['disabled_at' => now()]);

        $this->post(route('external-review.comment', $token), ['name' => 'A', 'body' => 'B'])->assertRedirect(route('external-review.show', $token));
        $this->post(route('external-review.approve', $token), ['name' => 'A'])->assertRedirect(route('external-review.show', $token));
        expect(ReviewComment::query()->count())->toBe(0);
    });
});

describe('links sent before the move', function () {
    it('still open the review page', function () {
        $token = liveToken();

        $this->get('/?dcsReviewToken='.$token)->assertRedirect(route('external-review.show', $token));
    });

    it('leave the root address going to the dashboard', function () {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/?dcsReviewToken=../../etc')->assertRedirect('/dashboard');
    });
});
