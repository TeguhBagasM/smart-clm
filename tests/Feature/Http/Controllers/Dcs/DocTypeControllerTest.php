<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;

function dcsAdmin(?Tenant $tenant = null): User
{
    return User::factory()->for($tenant ?? Tenant::factory()->create())->role(SystemRole::Admin)->create();
}

describe('index', function () {
    it('answers 401 without a session', function () {
        $this->getJson('/api/dcs/doc-types')->assertUnauthorized();
    });

    it('seeds the five default document types on the first request of a tenant', function () {
        $user = User::factory()->role(SystemRole::Viewer)->create();

        $response = dcsAs($user)->getJson('/api/dcs/doc-types')->assertOk();

        expect(collect($response->json())->pluck('code')->all())->toBe(['SOP', 'IK', 'POL', 'MEMO', 'STD']);
    });

    it('returns each type in the Node shape', function () {
        $user = User::factory()->role(SystemRole::Viewer)->create();

        $sop = collect(dcsAs($user)->getJson('/api/dcs/doc-types')->json())->firstWhere('code', 'SOP');

        expect(array_keys($sop))->toEqualCanonicalizing([
            'id', 'code', 'name', 'description', 'defaultSections', 'signatureColumns', 'signatureGroups', 'layoutStyle',
            'letterhead', 'defaultLanes', 'defaultClauseIds', 'headerStyle', 'documentLevel', 'processOwner',
        ])->and($sop['name'])->toBe('Standard Operating Procedure')
            ->and($sop['description'])->toBeNull()
            ->and($sop['layoutStyle'])->toBe('standard')
            ->and($sop['headerStyle'])->toBe('iso')
            ->and($sop['documentLevel'])->toBe('Internal')
            ->and($sop['defaultSections'][0])->toBe(['heading' => '1. Definisi', 'content' => ''])
            ->and($sop['signatureGroups'])->toBe([['columns' => [
                ['label' => 'Penyusun', 'role' => 'staff'],
                ['label' => 'Mengetahui', 'role' => 'manajemen'],
                ['label' => 'Menyetujui', 'role' => 'legal'],
            ]]])
            ->and($sop['signatureColumns'])->toBe($sop['signatureGroups'][0]['columns'])
            ->and($sop['defaultLanes'])->toBe(['Penyusun', 'Mengetahui', 'Menyetujui']);
    });

    it('seeds the memo with grouped signers and a simple header', function () {
        $user = User::factory()->role(SystemRole::Viewer)->create();

        $memo = collect(dcsAs($user)->getJson('/api/dcs/doc-types')->json())->firstWhere('code', 'MEMO');

        expect($memo['layoutStyle'])->toBe('memo')
            ->and($memo['headerStyle'])->toBe('simple')
            ->and($memo['letterhead'])->toBeTrue()
            ->and(collect($memo['signatureGroups'])->pluck('heading')->all())->toBe(['Penyusun:', 'Mengetahui:'])
            ->and($memo['signatureColumns'])->toHaveCount(4);
    });

    it('lists active types only, oldest first, of the own tenant only', function () {
        $user = User::factory()->role(SystemRole::Viewer)->create();
        app(SeedDcsTenantDefaults::class)->handle($user->tenant);
        $own = DocumentType::factory()->for($user->tenant)->create(['code' => 'ZZ']);
        DocumentType::factory()->for($user->tenant)->inactive()->create(['code' => 'OLD']);
        DocumentType::factory()->create(['code' => 'OTHER']);

        $codes = collect(dcsAs($user)->getJson('/api/dcs/doc-types')->json())->pluck('code')->all();

        expect($codes)->toBe(['SOP', 'IK', 'POL', 'MEMO', 'STD', 'ZZ']);
    });

    it('requires a tenant choice from a super admin and honours it', function () {
        $tenant = Tenant::factory()->create(['legacy_id' => 't-77']);
        $superAdmin = User::factory()->superAdmin()->create();

        dcsAs($superAdmin)->getJson('/api/dcs/doc-types')->assertStatus(400)->assertJsonPath('error', 'Pilih perusahaan (tenantId) terlebih dahulu.');
        dcsAs($superAdmin)->getJson('/api/dcs/doc-types?tenantId=t-77')->assertOk();
        dcsAs($superAdmin)->getJson('/api/dcs/doc-types?tenantId=t-404')->assertNotFound()->assertJsonPath('error', 'Perusahaan tidak ditemukan.');

        expect(DocumentType::query()->where('tenant_id', $tenant->id)->count())->toBe(5);
    });
});

describe('store', function () {
    it('forbids users without dcs.config', function (SystemRole $role) {
        dcsAs(User::factory()->role($role)->create())
            ->postJson('/api/dcs/doc-types', ['code' => 'SM', 'name' => 'Standar Mutu'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'Anda tidak memiliki izin untuk tindakan ini.']);
    })->with([SystemRole::Viewer, SystemRole::HrGa, SystemRole::ItSystem]);

    it('creates a type with the default signers and normalises the code', function () {
        $admin = dcsAdmin();

        $response = dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'sm-1!', 'name' => 'Standar Mutu'])->assertOk();

        $type = $response->json('docType');
        expect($response->json('success'))->toBeTrue()
            ->and($type['code'])->toBe('SM1')
            ->and($type['layoutStyle'])->toBe('standard')
            ->and($type['signatureColumns'])->toHaveCount(3)
            ->and($type['defaultSections'])->toBe([]);
        expect(AuditLog::query()->where('event', 'dcs.doc_type.created')->where('tenant_id', $admin->tenant_id)->exists())->toBeTrue();
    });

    it('stores a custom template', function () {
        $clause = Clause::factory()->create();
        $admin = dcsAdmin($clause->tenant);

        $type = dcsAs($admin)->postJson('/api/dcs/doc-types', [
            'code' => 'AUD', 'name' => 'Audit', 'description' => 'Laporan audit', 'layoutStyle' => 'kebijakan', 'letterhead' => true,
            'documentLevel' => 'Rahasia', 'processOwner' => 'Mutu',
            'defaultSections' => [['heading' => '1. Temuan', 'content' => 'Isi'], ['heading' => '2. Saran']],
            'signatureGroups' => [
                ['heading' => 'Penyusun:', 'columns' => [['label' => 'Auditor', 'role' => 'legal', 'roleName' => 'Lead Auditor']]],
                ['columns' => [['label' => 'Direksi', 'role' => 'direktur']]],
            ],
            'defaultLanes' => ['Auditor', 'Direksi'],
            'defaultClauseIds' => [$clause->uuid],
        ])->assertOk()->json('docType');

        expect($type['layoutStyle'])->toBe('kebijakan')
            ->and($type['headerStyle'])->toBe('iso')
            ->and($type['letterhead'])->toBeTrue()
            ->and($type['description'])->toBe('Laporan audit')
            ->and($type['documentLevel'])->toBe('Rahasia')
            ->and($type['processOwner'])->toBe('Mutu')
            ->and($type['defaultSections'])->toBe([['heading' => '1. Temuan', 'content' => 'Isi'], ['heading' => '2. Saran', 'content' => '']])
            ->and($type['signatureGroups'])->toBe([
                ['heading' => 'Penyusun:', 'columns' => [['label' => 'Auditor', 'role' => 'legal', 'roleName' => 'Lead Auditor']]],
                ['columns' => [['label' => 'Direksi', 'role' => 'direktur']]],
            ])
            ->and($type['defaultLanes'])->toBe(['Auditor', 'Direksi'])
            ->and($type['defaultClauseIds'])->toBe([$clause->uuid]);
    });

    it('wraps legacy flat signature columns into one group', function () {
        $type = dcsAs(dcsAdmin())->postJson('/api/dcs/doc-types', [
            'code' => 'FLAT', 'name' => 'Flat', 'signatureColumns' => [['label' => 'Dibuat', 'role' => 'staff'], ['label' => 'Disetujui', 'role' => 'direktur']],
        ])->json('docType');

        expect($type['signatureGroups'])->toHaveCount(1)->and($type['signatureColumns'])->toHaveCount(2);
    });

    it('rejects a duplicate code with 409, even of a deactivated type', function () {
        $admin = dcsAdmin();
        dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'DUP', 'name' => 'Satu'])->assertOk();
        $id = DocumentType::query()->where('code', 'DUP')->value('uuid');
        dcsAs($admin)->deleteJson("/api/dcs/doc-types/{$id}")->assertOk();

        dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'dup', 'name' => 'Dua'])
            ->assertStatus(409)->assertExactJson(['error' => 'Kode jenis dokumen sudah dipakai.']);
    });

    it('allows the same code in another tenant', function () {
        dcsAs(dcsAdmin())->postJson('/api/dcs/doc-types', ['code' => 'SOP2', 'name' => 'A'])->assertOk();
        dcsAs(dcsAdmin())->postJson('/api/dcs/doc-types', ['code' => 'SOP2', 'name' => 'B'])->assertOk();
    });

    it('rejects missing or invalid input with 400 and the Node message', function (array $payload, string $message) {
        dcsAs(dcsAdmin())->postJson('/api/dcs/doc-types', $payload)->assertStatus(400)->assertJsonPath('error', $message);
    })->with([
        'no code' => [['name' => 'X'], 'Kode dan nama jenis dokumen wajib diisi'],
        'no name' => [['code' => 'X'], 'Kode dan nama jenis dokumen wajib diisi'],
        'code without letters or digits' => [['code' => '--', 'name' => 'X'], 'Kode dan nama jenis dokumen wajib diisi'],
        'unknown role' => [['code' => 'X', 'name' => 'X', 'signatureGroups' => [['columns' => [['label' => 'A', 'role' => 'nonexistent']]]]], 'Peran tidak dikenal: nonexistent.'],
        'unknown clause' => [['code' => 'X', 'name' => 'X', 'defaultClauseIds' => ['6f1f9f0a-0000-4000-8000-000000000000']], 'Klausul tidak ditemukan: 6f1f9f0a-0000-4000-8000-000000000000.'],
        'invalid layout' => [['code' => 'X', 'name' => 'X', 'layoutStyle' => 'fancy'], 'The selected layout style is invalid.'],
    ]);

    it('rejects clauses of another tenant', function () {
        $foreign = Clause::factory()->create();

        dcsAs(dcsAdmin())->postJson('/api/dcs/doc-types', ['code' => 'X', 'name' => 'X', 'defaultClauseIds' => [$foreign->uuid]])->assertStatus(400);
    });
});

describe('update', function () {
    it('changes only the supplied fields and keeps the rest', function () {
        $admin = dcsAdmin();
        $id = dcsAs($admin)->postJson('/api/dcs/doc-types', [
            'code' => 'UPD', 'name' => 'Lama', 'description' => 'Deskripsi', 'defaultSections' => [['heading' => 'Bab 1']],
        ])->json('docType.id');

        $type = dcsAs($admin)->putJson("/api/dcs/doc-types/{$id}", ['name' => 'Baru', 'processOwner' => 'HRD'])->assertOk()->json('docType');

        expect($type['name'])->toBe('Baru')
            ->and($type['processOwner'])->toBe('HRD')
            ->and($type['description'])->toBe('Deskripsi')
            ->and($type['code'])->toBe('UPD')
            ->and($type['defaultSections'])->toHaveCount(1)
            ->and($type['signatureColumns'])->toHaveCount(3);
        expect(AuditLog::query()->where('event', 'dcs.doc_type.updated')->exists())->toBeTrue();
    });

    it('replaces sections and signature steps when supplied', function () {
        $admin = dcsAdmin();
        $id = dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'REP', 'name' => 'Rep', 'defaultSections' => [['heading' => 'A'], ['heading' => 'B']]])->json('docType.id');

        $type = dcsAs($admin)->putJson("/api/dcs/doc-types/{$id}", [
            'defaultSections' => [['heading' => 'C']],
            'signatureColumns' => [['label' => 'Satu-satunya', 'role' => 'direktur']],
        ])->json('docType');

        expect($type['defaultSections'])->toBe([['heading' => 'C', 'content' => '']])
            ->and($type['signatureColumns'])->toBe([['label' => 'Satu-satunya', 'role' => 'direktur']]);
    });

    it('restores the default signers when given an empty list of groups', function () {
        $admin = dcsAdmin();
        $id = dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'EMP', 'name' => 'Emp', 'signatureColumns' => [['label' => 'X', 'role' => 'legal']]])->json('docType.id');

        $type = dcsAs($admin)->putJson("/api/dcs/doc-types/{$id}", ['signatureGroups' => []])->json('docType');

        expect($type['signatureColumns'])->toHaveCount(3);
    });

    it('does not clear a description', function () {
        $admin = dcsAdmin();
        $id = dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'DSC', 'name' => 'Dsc', 'description' => 'Tetap'])->json('docType.id');

        $type = dcsAs($admin)->putJson("/api/dcs/doc-types/{$id}", ['description' => ''])->json('docType');

        expect($type['description'])->toBe('Tetap');
    });

    it('answers 404 for a type of another tenant', function () {
        $foreign = DocumentType::factory()->create();

        dcsAs(dcsAdmin())->putJson("/api/dcs/doc-types/{$foreign->uuid}", ['name' => 'X'])
            ->assertNotFound()->assertExactJson(['error' => 'Jenis dokumen tidak ditemukan']);
        expect($foreign->fresh()->name)->not->toBe('X');
    });

    it('forbids users without dcs.config', function () {
        $type = DocumentType::factory()->create();

        dcsAs(User::factory()->for($type->tenant)->role(SystemRole::Viewer)->create())
            ->putJson("/api/dcs/doc-types/{$type->uuid}", ['name' => 'X'])->assertForbidden();
    });
});

describe('destroy', function () {
    it('deactivates the type and hides it from the list', function () {
        $admin = dcsAdmin();
        $id = dcsAs($admin)->postJson('/api/dcs/doc-types', ['code' => 'GONE', 'name' => 'Gone'])->json('docType.id');

        dcsAs($admin)->deleteJson("/api/dcs/doc-types/{$id}")->assertOk()->assertExactJson(['success' => true]);

        expect(collect(dcsAs($admin)->getJson('/api/dcs/doc-types')->json())->pluck('code'))->not->toContain('GONE')
            ->and(DocumentType::query()->where('uuid', $id)->value('is_active'))->toBeFalse();
        expect(AuditLog::query()->where('event', 'dcs.doc_type.deactivated')->exists())->toBeTrue();
    });

    it('does nothing for a type of another tenant', function () {
        $foreign = DocumentType::factory()->create();

        dcsAs(dcsAdmin())->deleteJson("/api/dcs/doc-types/{$foreign->uuid}")->assertOk()->assertExactJson(['success' => true]);

        expect($foreign->fresh()->is_active)->toBeTrue();
    });

    it('answers 404 for an id that is not a uuid', function () {
        dcsAs(dcsAdmin())->deleteJson('/api/dcs/doc-types/not-a-uuid')->assertNotFound();
        dcsAs(dcsAdmin())->putJson('/api/dcs/doc-types/not-a-uuid', ['name' => 'X'])->assertNotFound();
    });
});
