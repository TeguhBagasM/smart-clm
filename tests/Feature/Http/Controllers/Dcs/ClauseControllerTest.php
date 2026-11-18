<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;

function clauseAdmin(?Tenant $tenant = null): User
{
    return User::factory()->for($tenant ?? Tenant::factory()->create())->role(SystemRole::Admin)->create();
}

describe('index', function () {
    it('answers 401 without a session', function () {
        $this->getJson('/api/dcs/clauses')->assertUnauthorized();
    });

    it('lists active clauses of the tenant by category then creation, in the Node shape', function () {
        $admin = clauseAdmin();
        Clause::factory()->for($admin->tenant)->create(['title' => 'B', 'category' => 'Umum', 'tags' => ['x']]);
        Clause::factory()->for($admin->tenant)->create(['title' => 'A', 'category' => 'Audit']);
        Clause::factory()->for($admin->tenant)->inactive()->create(['title' => 'Gone']);
        Clause::factory()->create(['title' => 'Foreign']);

        $clauses = dcsAs($admin)->getJson('/api/dcs/clauses')->assertOk()->json();

        expect(collect($clauses)->pluck('title')->all())->toBe(['A', 'B'])
            ->and(array_keys($clauses[1]))->toEqualCanonicalizing(['id', 'title', 'content', 'category', 'applicableDocTypeCodes', 'isMandatory', 'tags'])
            ->and($clauses[1]['tags'])->toBe(['x']);
    });

    it('filters by document type code: clauses for that type and clauses for all', function () {
        $admin = clauseAdmin();
        $sop = DocumentType::factory()->for($admin->tenant)->create(['code' => 'SOP']);
        $ik = DocumentType::factory()->for($admin->tenant)->create(['code' => 'IK']);
        $forAll = Clause::factory()->for($admin->tenant)->create(['title' => 'Semua']);
        $forSop = Clause::factory()->for($admin->tenant)->create(['title' => 'Khusus SOP']);
        $forSop->documentTypes()->attach($sop);
        $forIk = Clause::factory()->for($admin->tenant)->create(['title' => 'Khusus IK']);
        $forIk->documentTypes()->attach($ik);

        $titles = collect(dcsAs($admin)->getJson('/api/dcs/clauses?docTypeCode=SOP')->json())->pluck('title')->sort()->values()->all();

        expect($titles)->toBe(['Khusus SOP', 'Semua'])
            ->and(dcsAs($admin)->getJson('/api/dcs/clauses')->json())->toHaveCount(3);
    });
});

describe('store', function () {
    it('creates a clause with the default category and records who created it', function () {
        $admin = clauseAdmin();

        $clause = dcsAs($admin)->postJson('/api/dcs/clauses', ['title' => 'Kerahasiaan', 'content' => 'Isi klausul'])->assertOk()->json('clause');

        expect($clause['category'])->toBe('General')
            ->and($clause['isMandatory'])->toBeFalse()
            ->and($clause['tags'])->toBe([])
            ->and($clause['applicableDocTypeCodes'])->toBe([]);
        expect(Clause::query()->where('uuid', $clause['id'])->value('created_by_id'))->toBe($admin->id);
        expect(AuditLog::query()->where('event', 'dcs.clause.created')->exists())->toBeTrue();
    });

    it('attaches document types by code', function () {
        $admin = clauseAdmin();
        DocumentType::factory()->for($admin->tenant)->create(['code' => 'SOP']);
        DocumentType::factory()->for($admin->tenant)->create(['code' => 'IK']);

        $clause = dcsAs($admin)->postJson('/api/dcs/clauses', [
            'title' => 'T', 'content' => 'C', 'category' => 'Audit', 'isMandatory' => true, 'tags' => ['a', 'b'], 'applicableDocTypeCodes' => ['SOP', 'IK'],
        ])->assertOk()->json('clause');

        expect($clause['applicableDocTypeCodes'])->toBe(['IK', 'SOP'])
            ->and($clause['isMandatory'])->toBeTrue()
            ->and($clause['category'])->toBe('Audit');
    });

    it('rejects unknown document type codes instead of widening the clause to all types', function () {
        $admin = clauseAdmin();
        DocumentType::factory()->create(['code' => 'FOREIGN']);

        dcsAs($admin)->postJson('/api/dcs/clauses', ['title' => 'T', 'content' => 'C', 'applicableDocTypeCodes' => ['FOREIGN']])
            ->assertStatus(400)->assertJsonPath('error', 'Kode jenis dokumen tidak dikenal: FOREIGN.');
        expect(Clause::query()->count())->toBe(0);
    });

    it('requires a title and content with the Node message', function (array $payload) {
        dcsAs(clauseAdmin())->postJson('/api/dcs/clauses', $payload)->assertStatus(400)->assertJsonPath('error', 'Judul dan isi klausul wajib diisi');
    })->with([[['content' => 'x']], [['title' => 'x']]]);

    it('forbids users without dcs.config', function () {
        dcsAs(User::factory()->role(SystemRole::Viewer)->create())->postJson('/api/dcs/clauses', ['title' => 'T', 'content' => 'C'])->assertForbidden();
    });
});

describe('update', function () {
    it('changes only the supplied fields', function () {
        $admin = clauseAdmin();
        $clause = Clause::factory()->for($admin->tenant)->create(['title' => 'Lama', 'content' => 'Isi', 'category' => 'Umum', 'is_mandatory' => true]);

        $updated = dcsAs($admin)->putJson("/api/dcs/clauses/{$clause->uuid}", ['title' => 'Baru'])->assertOk()->json('clause');

        expect($updated['title'])->toBe('Baru')
            ->and($updated['content'])->toBe('Isi')
            ->and($updated['category'])->toBe('Umum')
            ->and($updated['isMandatory'])->toBeTrue();
        expect(AuditLog::query()->where('event', 'dcs.clause.updated')->exists())->toBeTrue();
    });

    it('keeps attached types when none are supplied and clears them with an empty list', function () {
        $admin = clauseAdmin();
        $sop = DocumentType::factory()->for($admin->tenant)->create(['code' => 'SOP']);
        $clause = Clause::factory()->for($admin->tenant)->create();
        $clause->documentTypes()->attach($sop);

        expect(dcsAs($admin)->putJson("/api/dcs/clauses/{$clause->uuid}", ['title' => 'X'])->json('clause.applicableDocTypeCodes'))->toBe(['SOP'])
            ->and(dcsAs($admin)->putJson("/api/dcs/clauses/{$clause->uuid}", ['applicableDocTypeCodes' => []])->json('clause.applicableDocTypeCodes'))->toBe([]);
    });

    it('answers 404 for a clause of another tenant', function () {
        $foreign = Clause::factory()->create(['title' => 'Asli']);

        dcsAs(clauseAdmin())->putJson("/api/dcs/clauses/{$foreign->uuid}", ['title' => 'X'])->assertNotFound()->assertExactJson(['error' => 'Klausul tidak ditemukan']);
        expect($foreign->fresh()->title)->toBe('Asli');
    });
});

describe('destroy', function () {
    it('deactivates the clause', function () {
        $admin = clauseAdmin();
        $clause = Clause::factory()->for($admin->tenant)->create();

        dcsAs($admin)->deleteJson("/api/dcs/clauses/{$clause->uuid}")->assertOk()->assertExactJson(['success' => true]);

        expect($clause->fresh()->is_active)->toBeFalse()
            ->and(dcsAs($admin)->getJson('/api/dcs/clauses')->json())->toBe([]);
        expect(AuditLog::query()->where('event', 'dcs.clause.deactivated')->exists())->toBeTrue();
    });

    it('does nothing for a clause of another tenant', function () {
        $foreign = Clause::factory()->create();

        dcsAs(clauseAdmin())->deleteJson("/api/dcs/clauses/{$foreign->uuid}")->assertOk();

        expect($foreign->fresh()->is_active)->toBeTrue();
    });
});
