<?php

use App\Actions\Dcs\CreateDocument;
use App\Actions\Dcs\CreateVersion;
use App\Actions\Dcs\SaveNumberingRule;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\NumberingMask;

const TOKENS_2026 = ['DocType' => 'SOP', 'Department' => 'HED', 'Year' => 2026, 'Month' => 3, 'Day' => 28];

describe('mask', function () {
    it('writes the example number SOP-HED-27-00-26 and keeps a number that outgrows its minimum length whole', function () {
        expect(NumberingMask::render(NumberingMask::SUGGESTED_MASK, TOKENS_2026, 27))->toBe('SOP-HED-27-00-26')
            ->and(NumberingMask::render(NumberingMask::SUGGESTED_MASK, TOKENS_2026, 27, 1))->toBe('SOP-HED-27-01-26')
            ->and(NumberingMask::render(NumberingMask::SUGGESTED_MASK, TOKENS_2026, 5))->toBe('SOP-HED-05-00-26')
            ->and(NumberingMask::render(NumberingMask::SUGGESTED_MASK, TOKENS_2026, 100, 12))->toBe('SOP-HED-100-12-26')
            ->and(NumberingMask::render('{Seq:2}-{Rev:2}', TOKENS_2026, 1234, 123))->toBe('1234-123');
    });

    it('knows the long and short forms of the tokens, and the older {Sequence}', function () {
        expect(NumberingMask::render('{DocType}/{Department}/{YYYY}/{MM}/{Sequence:4}', TOKENS_2026, 7))->toBe('SOP/HED/2026/03/0007')
            ->and(NumberingMask::render('{DocType}/{Dept}/{Year}/{Sequence:4}', TOKENS_2026, 7))->toBe('SOP/HED/2026/0007');
    });

    it('gives the number without its revision, whichever end the revision is on', function () {
        expect(NumberingMask::renderBase(NumberingMask::SUGGESTED_MASK, TOKENS_2026, 27))->toBe('SOP-HED-27-26')
            ->and(NumberingMask::renderBase('{DocType}-{Seq:3}-{Rev:2}', TOKENS_2026, 4))->toBe('SOP-004')
            ->and(NumberingMask::renderBase('{Rev:2}-{DocType}-{Seq:3}', TOKENS_2026, 4))->toBe('SOP-004')
            ->and(NumberingMask::renderBase(NumberingMask::DEFAULT_MASK, TOKENS_2026, 4))->toBe('SOP/HED/2026/0004');
    });

    it('accepts the new tokens and refuses unknown ones, repeats and absurd lengths', function () {
        expect(NumberingMask::validate(NumberingMask::SUGGESTED_MASK))->toBeNull()
            ->and(NumberingMask::validate(NumberingMask::DEFAULT_MASK))->toBeNull()
            ->and(NumberingMask::validate('{DocType}-{Dept}-{Rev:2}'))->toContain('{Sequence}')
            ->and(NumberingMask::validate('{DocType}-{Seq:2}-{Bogus}'))->toContain('"{Bogus}"')
            ->and(NumberingMask::validate('{Seq:2}-{Seq:3}'))->toContain('hanya boleh dipakai sekali')
            ->and(NumberingMask::validate('{Seq:2}-{Rev}-{Rev}'))->toContain('hanya boleh dipakai sekali')
            ->and(NumberingMask::validate('{Seq}{Sequence}'))->not->toBeNull()
            ->and(NumberingMask::validate('{Seq:13}'))->toContain('terlalu besar');
    });
});

describe('documents', function () {
    beforeEach(function () {
        $this->travelTo('2026-03-28 10:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
        app(SeedDcsTenantDefaults::class)->handle($this->tenant);
        app(SaveNumberingRule::class)->handle($this->admin, $this->tenant, NumberingMask::SUGGESTED_MASK, null);
        $this->create = fn (string $department = 'HED', array $extra = []) => app(CreateDocument::class)->handle($this->admin, $this->tenant, [
            'docTypeCode' => 'SOP', 'department' => $department, 'title' => 'Uji', 'ownerUserId' => null, 'manualNumber' => null,
            'archiveCategory' => null, 'archiveSubFolderId' => null, ...$extra,
        ]);
    });

    it('numbers a document with the pattern, keeps the number without revision for searching, and counts per type and year', function () {
        ['document' => $first, 'version' => $version] = ($this->create)('HED');
        ['document' => $second] = ($this->create)('HRD');

        expect($first->document_number)->toBe('SOP-HED-01-00-26')
            ->and($first->base_number)->toBe('SOP-HED-01-26')
            ->and($version->number_label)->toBe('SOP-HED-01-00-26')
            ->and($second->document_number)->toBe('SOP-HRD-02-00-26');
    });

    it('writes the number of a new revision with the same pattern, and the base number stays', function () {
        ['document' => $document] = ($this->create)('HED');

        $revision = app(CreateVersion::class)->handle($this->admin, $document, 'revisi', 'Perbaikan');
        $again = app(CreateVersion::class)->handle($this->admin, $document, 'revisi', 'Lagi');

        expect($revision->number_label)->toBe('SOP-HED-01-01-26')
            ->and($again->number_label)->toBe('SOP-HED-01-02-26')
            ->and($document->refresh()->document_number)->toBe('SOP-HED-01-00-26')
            ->and($document->base_number)->toBe('SOP-HED-01-26');
    });

    it('does not change an issued number when the pattern is edited, and numbers later revisions the old way', function () {
        ['document' => $old] = ($this->create)('HED');

        app(SaveNumberingRule::class)->handle($this->admin, $this->tenant, '{Seq:4}/{Rev:2}/{DocType}', null);
        ['document' => $new] = ($this->create)('HED');
        $revision = app(CreateVersion::class)->handle($this->admin, $old, 'revisi', null);

        expect($old->refresh()->document_number)->toBe('SOP-HED-01-00-26')
            ->and($revision->number_label)->toBe('SOP-HED-01-01-26')
            ->and($new->document_number)->toBe('0002/00/SOP');
    });

    it('keeps the counter of a rule that already numbered documents, and gives a fresh rule the scope of its mask', function () {
        ($this->create)('HED');
        $rule = $this->tenant->dcsNumberingRules()->first() ?? NumberingRule::query()->where('tenant_id', $this->tenant->id)->whereNull('doc_type_id')->first();

        expect($rule->scope_tokens)->toBe(NumberingMask::SUGGESTED_SCOPE_TOKENS);

        app(SaveNumberingRule::class)->handle($this->admin, $this->tenant, NumberingMask::DEFAULT_MASK, null);

        expect($rule->refresh()->scope_tokens)->toBe(NumberingMask::SUGGESTED_SCOPE_TOKENS);
    });

    it('is found by the number without its revision as well as by the full number', function () {
        ['document' => $document] = ($this->create)('HED');
        app(CreateVersion::class)->handle($this->admin, $document, 'revisi', null);

        foreach (['SOP-HED-01-26', 'sop-hed-01-00-26'] as $term) {
            $this->actingAs($this->admin)->get(route('documents.index', ['search' => $term]))
                ->assertInertia(fn ($page) => $page->has('documents.data', 1)->where('documents.data.0.number', 'SOP-HED-01-00-26'));
        }
    });

    it('creates departments in capitals', function () {
        ['document' => $document] = ($this->create)('hrd');

        expect($document->department->code)->toBe('HRD')->and($document->document_number)->toBe('SOP-HRD-01-00-26');
    });
});
