<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
});

function typeToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

it('refuses a duplicate code in any case, even of a switched-off type', function () {
    $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'DUP', 'name' => 'Satu'])->assertRedirect();
    $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'DUP')->sole();
    $this->actingAs($this->admin)->delete(route('documents.config.types.destroy', $type->uuid))->assertRedirect();

    $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'dup', 'name' => 'Dua'])->assertRedirect();

    expect(typeToast())->toBe(['type' => 'error', 'message' => 'Kode jenis dokumen sudah dipakai.'])
        ->and(DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'DUP')->count())->toBe(1);
});

it('allows the same code in another company', function () {
    $other = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($other);
    $otherAdmin = User::factory()->for($other)->role(SystemRole::Admin)->create();

    $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'SOP2', 'name' => 'A']);
    $this->actingAs($otherAdmin)->post(route('documents.config.types.store'), ['code' => 'SOP2', 'name' => 'B']);

    expect(DocumentType::query()->where('code', 'SOP2')->count())->toBe(2);
});

it('refuses default clauses of another company', function () {
    $foreign = Clause::factory()->create();

    $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'X1', 'name' => 'X', 'defaultClauseIds' => [$foreign->uuid]]);

    expect(DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'X1')->exists())->toBeFalse();
});

it('restores the default signers when given an empty list of groups', function () {
    $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'EMP', 'name' => 'Emp', 'signatureColumns' => [['label' => 'X', 'role' => 'legal']]]);
    $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'EMP')->sole();
    expect(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->count())->toBe(1);

    $this->actingAs($this->admin)->put(route('documents.config.types.update', $type->uuid), ['name' => 'Emp', 'signatureGroups' => []])->assertRedirect();

    expect(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->count())->toBe(3);
});
