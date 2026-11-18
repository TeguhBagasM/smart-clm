<?php

use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\ReviewComment;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array<string, mixed>>  $versions  attributes per version; the first is 1.0, the next 2.0, ...
 */
function listDocument(User $owner, array $attributes = [], array $versions = [['status' => VersionStatus::Draft]]): Document
{
    $document = Document::factory()->for($owner->tenant)->create(['owner_user_id' => $owner->id, 'created_by_id' => $owner->id, ...$attributes]);

    foreach ($versions as $index => $version) {
        $created = DocumentVersion::factory()->for($document)->create(['major' => $index + 1, 'minor' => 0, 'created_by_id' => $owner->id, ...$version]);

        if (($version['status'] ?? null) === VersionStatus::Effective) {
            $document->forceFill(['current_version_id' => $created->id])->save();
        }
    }

    return $document->fresh();
}

function numbers(array $query = [], ?User $user = null)
{
    return test()->actingAs($user ?? test()->admin)->get(route('documents.index', $query));
}

function rowNumbers($response): array
{
    $numbers = [];
    $response->assertInertia(function (Assert $page) use (&$numbers) {
        $numbers = collect($page->toArray()['props']['documents']['data'])->pluck('number')->all();
    });

    return $numbers;
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
});

describe('access', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    });

    it('shows the list with the list definition and rows without internal ids', function () {
        listDocument($this->admin, ['document_number' => 'SOP/HRD/2026/0001', 'title' => 'Prosedur Cuti']);

        numbers()->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.number', 'SOP/HRD/2026/0001')
            ->where('documents.data.0.latest_version.status', 'draft')
            ->missing('documents.data.0.owner_user_id')
            ->where('list.sort', 'updated_at')->where('list.direction', 'desc')->where('list.per_page', 25)->where('list.active_count', 0)
            ->has('list.sorts', 6)->has('list.filters')->where('list.per_page_options', [10, 25, 50, 100]),
        );
    });

    it('shows only the documents of the viewer\'s own company, and everything to a super admin', function () {
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        listDocument($this->admin, ['document_number' => 'MINE']);
        listDocument($other, ['document_number' => 'THEIRS']);

        expect(rowNumbers(numbers()))->toBe(['MINE']);
        expect(rowNumbers(numbers(user: User::factory()->superAdmin()->create())))->toEqualCanonicalizing(['MINE', 'THEIRS']);
    });

    it('offers the company filter to a super admin only, and filters by it', function () {
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        listDocument($this->admin, ['document_number' => 'MINE']);
        listDocument($other, ['document_number' => 'THEIRS']);
        $super = User::factory()->superAdmin()->create();

        numbers()->assertInertia(fn (Assert $page) => $page->where('list.filters', fn ($filters) => ! collect($filters)->contains('key', 'tenant')));
        $response = numbers(['filter' => ['tenant' => (string) $other->tenant_id]], $super);

        expect(rowNumbers($response))->toBe(['THEIRS']);
    });

    it('only offers types and departments of the viewer\'s company', function () {
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        DocumentType::factory()->for($this->tenant)->create(['code' => 'MINE']);
        DocumentType::factory()->for($other->tenant)->create(['code' => 'ALIEN']);
        Department::factory()->for($other->tenant)->create(['code' => 'ZZZ']);

        numbers()->assertInertia(fn (Assert $page) => $page->where('list.filters', function ($filters) {
            $type = collect($filters)->firstWhere('key', 'type');
            $department = collect($filters)->firstWhere('key', 'department');

            return collect($type['options'])->pluck('value')->contains('MINE') && ! collect($type['options'])->pluck('value')->contains('ALIEN')
                && ! collect($department['options'])->pluck('value')->contains('ZZZ');
        }));
    });
});

describe('search', function () {
    it('finds by number or title, ignoring case', function () {
        listDocument($this->admin, ['document_number' => 'SOP/HRD/0001', 'title' => 'Pengadaan barang']);
        listDocument($this->admin, ['document_number' => 'IK/FIN/0002', 'title' => 'Cuti tahunan']);

        expect(rowNumbers(numbers(['search' => 'PENGADAAN'])))->toBe(['SOP/HRD/0001']);
        expect(rowNumbers(numbers(['search' => 'ik/fin'])))->toBe(['IK/FIN/0002']);
        expect(rowNumbers(numbers(['search' => 'tidak ada'])))->toBe([]);
    });

    it('treats percent and underscore as plain characters', function () {
        listDocument($this->admin, ['document_number' => 'A', 'title' => 'Diskon 50% karyawan']);
        listDocument($this->admin, ['document_number' => 'B', 'title' => 'Diskon 500 karyawan']);
        listDocument($this->admin, ['document_number' => 'C', 'title' => 'Form_cuti']);
        listDocument($this->admin, ['document_number' => 'D', 'title' => 'Formacuti']);

        expect(rowNumbers(numbers(['search' => '50%'])))->toBe(['A']);
        expect(rowNumbers(numbers(['search' => 'form_'])))->toBe(['C']);
    });
});

describe('filters', function () {
    it('filters by the status of the newest version, several at once', function () {
        listDocument($this->admin, ['document_number' => 'DRAFT'], [['status' => VersionStatus::Draft]]);
        listDocument($this->admin, ['document_number' => 'REVIEW'], [['status' => VersionStatus::UnderReview]]);
        listDocument($this->admin, ['document_number' => 'NEWER-DRAFT'], [['status' => VersionStatus::Effective], ['status' => VersionStatus::Draft]]);

        expect(rowNumbers(numbers(['filter' => ['status' => ['draft']]])))->toEqualCanonicalizing(['DRAFT', 'NEWER-DRAFT']);
        expect(rowNumbers(numbers(['filter' => ['status' => ['draft', 'under_review']]])))->toHaveCount(3);
        expect(rowNumbers(numbers(['filter' => ['status' => ['effective']]])))->toBe([]);
    });

    it('filters by type, department and owner', function () {
        $sop = DocumentType::factory()->for($this->tenant)->create(['code' => 'SOP']);
        $ik = DocumentType::factory()->for($this->tenant)->create(['code' => 'IK']);
        $hrd = Department::factory()->for($this->tenant)->create(['code' => 'HRD']);
        $fin = Department::factory()->for($this->tenant)->create(['code' => 'FIN']);
        $colleague = User::factory()->for($this->tenant)->create();
        listDocument($this->admin, ['document_number' => 'A', 'doc_type_id' => $sop->id, 'department_id' => $hrd->id]);
        listDocument($colleague, ['document_number' => 'B', 'doc_type_id' => $ik->id, 'department_id' => $fin->id]);

        expect(rowNumbers(numbers(['filter' => ['type' => ['IK']]])))->toBe(['B']);
        expect(rowNumbers(numbers(['filter' => ['department' => ['HRD', 'FIN']]])))->toHaveCount(2);
        expect(rowNumbers(numbers(['filter' => ['owner' => (string) $colleague->id]])))->toBe(['B']);
        expect(rowNumbers(numbers(['filter' => ['type' => ['SOP'], 'department' => ['FIN']]])))->toBe([]);
    });

    it('filters by whether a version is in force', function () {
        listDocument($this->admin, ['document_number' => 'LIVE'], [['status' => VersionStatus::Effective]]);
        listDocument($this->admin, ['document_number' => 'NOT-YET'], [['status' => VersionStatus::Draft]]);

        expect(rowNumbers(numbers(['filter' => ['in_force' => '1']])))->toBe(['LIVE']);
        expect(rowNumbers(numbers(['filter' => ['in_force' => '0']])))->toBe(['NOT-YET']);
    });

    it('filters by the review date of the version in force', function () {
        listDocument($this->admin, ['document_number' => 'LATE'], [['status' => VersionStatus::Effective, 'review_due_at' => now()->subDays(3)]]);
        listDocument($this->admin, ['document_number' => 'SOON'], [['status' => VersionStatus::Effective, 'review_due_at' => now()->addDays(10)]]);
        listDocument($this->admin, ['document_number' => 'LATER'], [['status' => VersionStatus::Effective, 'review_due_at' => now()->addDays(60)]]);
        listDocument($this->admin, ['document_number' => 'NONE'], [['status' => VersionStatus::Effective]]);

        expect(rowNumbers(numbers(['filter' => ['review' => 'overdue']])))->toBe(['LATE']);
        expect(rowNumbers(numbers(['filter' => ['review' => 'next_30']])))->toBe(['SOON']);
        expect(rowNumbers(numbers(['filter' => ['review' => 'next_90']])))->toEqualCanonicalizing(['SOON', 'LATER']);
        expect(rowNumbers(numbers(['filter' => ['review' => 'not_set']])))->toBe(['NONE']);
    });

    it('filters by date ranges, inclusive of both ends', function () {
        listDocument($this->admin, ['document_number' => 'JAN', 'created_at' => '2026-01-31 23:30:00'], [['status' => VersionStatus::Effective, 'effective_at' => '2026-01-10 08:00:00']]);
        listDocument($this->admin, ['document_number' => 'MAR', 'created_at' => '2026-03-01 00:10:00'], [['status' => VersionStatus::Effective, 'effective_at' => '2026-03-05 08:00:00']]);

        expect(rowNumbers(numbers(['filter' => ['created' => ['from' => '2026-01-31', 'to' => '2026-01-31']]])))->toBe(['JAN']);
        expect(rowNumbers(numbers(['filter' => ['created' => ['from' => '2026-02-01']]])))->toBe(['MAR']);
        expect(rowNumbers(numbers(['filter' => ['effective' => ['from' => '2026-01-01', 'to' => '2026-01-10']]])))->toBe(['JAN']);
        expect(rowNumbers(numbers(['filter' => ['effective' => ['to' => '2026-12-31']]])))->toHaveCount(2);
    });

    it('filters by file, open comments, outside review and scheduled release on the newest version', function () {
        $withFile = listDocument($this->admin, ['document_number' => 'FILE']);
        $commented = listDocument($this->admin, ['document_number' => 'COMMENT']);
        $shared = listDocument($this->admin, ['document_number' => 'SHARED']);
        $scheduled = listDocument($this->admin, ['document_number' => 'SCHEDULED'], [['status' => VersionStatus::Approved, 'scheduled_effective_at' => now()->addWeek()]]);
        listDocument($this->admin, ['document_number' => 'PLAIN']);

        Rendition::query()->forceCreate(['version_id' => $withFile->latestVersion->id, 'kind' => RenditionKind::Composed, 'storage_key' => 'k', 'sha256' => str_repeat('a', 64), 'size_bytes' => 1, 'mime_type' => 'application/pdf', 'created_by_id' => $this->admin->id]);
        ReviewComment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $commented->latestVersion->id, 'author_user_id' => $this->admin->id, 'author_name' => 'A', 'anchor' => [], 'body' => 'x', 'status' => 'open', 'review_round' => 1]);
        ExternalReview::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $shared->latestVersion->id, 'token_hash' => str_repeat('b', 64), 'locked' => false, 'created_by_id' => $this->admin->id]);

        expect(rowNumbers(numbers(['filter' => ['has_file' => '1']])))->toBe(['FILE']);
        expect(rowNumbers(numbers(['filter' => ['has_file' => '0']])))->toHaveCount(4);
        expect(rowNumbers(numbers(['filter' => ['open_comments' => 'true']])))->toBe(['COMMENT']);
        expect(rowNumbers(numbers(['filter' => ['external_review' => '1']])))->toBe(['SHARED']);
        expect(rowNumbers(numbers(['filter' => ['scheduled' => '1']])))->toBe(['SCHEDULED']);
        expect(rowNumbers(numbers(['filter' => ['scheduled' => '0']])))->toHaveCount(4);
        expect($scheduled->latestVersion->scheduled_effective_at)->not->toBeNull();
    });

    it('ignores invalid filter values instead of failing', function () {
        listDocument($this->admin, ['document_number' => 'A']);
        listDocument($this->admin, ['document_number' => 'B']);

        $response = numbers(['filter' => [
            'status' => ['nonsense'], 'type' => 'NOPE', 'owner' => 'not-an-id', 'review' => 'soonish', 'in_force' => 'maybe',
            'created' => ['from' => '2026-02-31', 'to' => 'tomorrow'], 'unknown' => '1',
        ]])->assertOk();

        expect(rowNumbers($response))->toHaveCount(2);
        $response->assertInertia(fn (Assert $page) => $page->where('list.active_count', 0));
    });

    it('reports the active filters and their values back', function () {
        listDocument($this->admin);

        numbers(['filter' => ['status' => ['draft'], 'in_force' => '0', 'created' => ['from' => '2026-01-01']]])->assertInertia(fn (Assert $page) => $page
            ->where('list.active_count', 3)
            ->where('list.filters', function ($filters) {
                $filters = collect($filters)->keyBy('key');

                return $filters['status']['value'] === ['draft'] && $filters['in_force']['value'] === false && $filters['created']['value'] === ['from' => '2026-01-01', 'to' => null];
            }));
    });
});

describe('sorting and paging', function () {
    it('sorts by number and title in both directions, and falls back on unknown input', function () {
        listDocument($this->admin, ['document_number' => 'B', 'title' => 'Zebra']);
        listDocument($this->admin, ['document_number' => 'A', 'title' => 'Alpha']);
        listDocument($this->admin, ['document_number' => 'C', 'title' => 'Mango']);

        expect(rowNumbers(numbers(['sort' => 'number', 'direction' => 'asc'])))->toBe(['A', 'B', 'C']);
        expect(rowNumbers(numbers(['sort' => 'title', 'direction' => 'desc'])))->toBe(['B', 'C', 'A']);
        numbers(['sort' => 'password', 'direction' => 'sideways'])->assertInertia(fn (Assert $page) => $page->where('list.sort', 'updated_at')->where('list.direction', 'desc'));
    });

    it('puts documents without a version in force last, whichever way the date sorts', function () {
        listDocument($this->admin, ['document_number' => 'EARLY'], [['status' => VersionStatus::Effective, 'effective_at' => '2026-01-01 00:00:00']]);
        listDocument($this->admin, ['document_number' => 'LATE'], [['status' => VersionStatus::Effective, 'effective_at' => '2026-06-01 00:00:00']]);
        listDocument($this->admin, ['document_number' => 'NONE'], [['status' => VersionStatus::Draft]]);

        expect(rowNumbers(numbers(['sort' => 'effective_at', 'direction' => 'asc'])))->toBe(['EARLY', 'LATE', 'NONE']);
        expect(rowNumbers(numbers(['sort' => 'effective_at', 'direction' => 'desc'])))->toBe(['LATE', 'EARLY', 'NONE']);
        expect(rowNumbers(numbers(['sort' => 'review_due_at', 'direction' => 'asc'])))->toHaveCount(3);
    });

    it('pages with the chosen size, keeps order stable across pages and carries the query along', function () {
        $stamp = now()->subDay();

        foreach (range(1, 12) as $n) {
            listDocument($this->admin, ['document_number' => sprintf('DOC-%02d', $n), 'updated_at' => $stamp]);
        }

        $first = rowNumbers(numbers(['per_page' => 10, 'sort' => 'updated_at', 'direction' => 'asc']));
        $second = rowNumbers(numbers(['per_page' => 10, 'sort' => 'updated_at', 'direction' => 'asc', 'page' => 2]));

        expect($first)->toHaveCount(10)->and($second)->toHaveCount(2)->and(array_merge($first, $second))->toHaveCount(12)->and(array_unique(array_merge($first, $second)))->toHaveCount(12);
        numbers(['per_page' => 10, 'filter' => ['status' => ['draft']]])->assertInertia(fn (Assert $page) => $page
            ->where('documents.meta.last_page', 2)->where('documents.meta.total', 12)
            ->where('documents.links.next', fn ($url) => str_contains($url, 'per_page=10') && str_contains(urldecode($url), 'filter[status][0]=draft')));
    });

    it('falls back to the default size for sizes the list does not offer', function () {
        listDocument($this->admin);

        numbers(['per_page' => 9999])->assertInertia(fn (Assert $page) => $page->where('list.per_page', 25));
    });
});
