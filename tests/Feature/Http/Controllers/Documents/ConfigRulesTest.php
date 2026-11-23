<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->sop = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'SOP')->sole();
});

describe('clauses', function () {
    it('refuses unknown document type codes instead of widening the clause to all types', function () {
        DocumentType::factory()->create(['code' => 'FOREIGN']);

        $this->actingAs($this->admin)->post(route('documents.config.clauses.store'), ['title' => 'T', 'content' => 'C', 'applicableDocTypeCodes' => ['FOREIGN']])
            ->assertSessionHasErrors(['applicableDocTypeCodes' => 'Kode jenis dokumen tidak dikenal: FOREIGN.']);

        expect(Clause::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    });
});

describe('numbering', function () {
    it('makes an override for a type with the default scope, then updates it in place', function () {
        $update = fn (string $mask) => $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => $mask, 'docTypeId' => $this->sop->uuid])->assertSessionHasNoErrors();

        $update('SOP/{Month}/{Sequence}');
        $first = NumberingRule::query()->where('tenant_id', $this->tenant->id)->where('doc_type_id', $this->sop->id)->sole();
        $update('SOP-{Sequence:3}');
        $second = NumberingRule::query()->where('tenant_id', $this->tenant->id)->where('doc_type_id', $this->sop->id)->sole();

        expect($first->scope_tokens)->toBe(['DocType', 'Department', 'Year'])->and($second->id)->toBe($first->id)->and($second->mask)->toBe('SOP-{Sequence:3}')
            ->and(NumberingRule::effectiveFor($this->tenant, null)->mask)->not->toBe('SOP-{Sequence:3}');
    });

    it('does nothing when a type has no override, and never removes the default rule', function () {
        $this->actingAs($this->admin)->delete(route('documents.config.numbering.destroy', $this->sop->uuid))->assertRedirect();

        expect(NumberingRule::query()->where('tenant_id', $this->tenant->id)->whereNull('doc_type_id')->count())->toBe(1)
            ->and(NumberingRule::effectiveFor($this->tenant, $this->sop))->not->toBeNull();
    });

    it('refuses a blank mask or one without a sequence, with the reason', function (string $mask, string $message) {
        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => $mask])->assertSessionHasErrors(['mask' => $message]);
    })->with([
        'blank' => ['   ', 'Format nomor tidak boleh kosong.'],
        'no sequence' => ['{DocType}/{Year}', 'Format nomor wajib memuat token {Sequence} atau {Sequence:N}.'],
    ]);
});
