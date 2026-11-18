<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;

function numberingAdmin(): User
{
    return User::factory()->for(Tenant::factory()->create(['legacy_id' => 't-nr']))->role(SystemRole::Admin)->create();
}

function typeId(User $user, string $code): string
{
    return collect(dcsAs($user)->getJson('/api/dcs/doc-types')->json())->firstWhere('code', $code)['id'];
}

describe('show', function () {
    it('returns the tenant default rule in the Node shape', function () {
        $user = numberingAdmin();

        $rule = dcsAs($user)->getJson('/api/dcs/numbering-rule')->assertOk()->json();

        expect(array_keys($rule))->toEqualCanonicalizing(['id', 'tenantId', 'mask', 'scopeTokens', 'isOverride'])
            ->and($rule['tenantId'])->toBe('t-nr')
            ->and($rule['mask'])->toBe('{DocType}/{Department}/{Year}/{Sequence:4}')
            ->and($rule['scopeTokens'])->toBe(['DocType', 'Department', 'Year'])
            ->and($rule['isOverride'])->toBeFalse();
    });

    it('falls back to the default for a type without override or an unknown type', function () {
        $user = numberingAdmin();
        $sop = typeId($user, 'SOP');

        dcsAs($user)->getJson("/api/dcs/numbering-rule?docTypeId={$sop}")->assertOk()->assertJsonPath('isOverride', false);
        dcsAs($user)->getJson('/api/dcs/numbering-rule?docTypeId=not-a-uuid')->assertOk()->assertJsonPath('isOverride', false);
    });

    it('is readable by any signed-in user and isolated per tenant', function () {
        $viewer = User::factory()->role(SystemRole::Viewer)->create();
        NumberingRule::factory()->for($viewer->tenant)->create();
        $other = numberingAdmin();
        dcsAs($other)->putJson('/api/dcs/numbering-rule', ['mask' => '{Sequence}/X'])->assertOk();

        dcsAs($viewer)->getJson('/api/dcs/numbering-rule')->assertOk()->assertJsonPath('mask', '{DocType}/{Department}/{Year}/{Sequence:4}');
    });
});

describe('update', function () {
    it('changes the default mask', function () {
        $admin = numberingAdmin();

        $response = dcsAs($admin)->putJson('/api/dcs/numbering-rule', ['mask' => 'DOC-{Year}-{Sequence:5}'])->assertOk();

        expect($response->json('success'))->toBeTrue()
            ->and($response->json('rule.mask'))->toBe('DOC-{Year}-{Sequence:5}')
            ->and($response->json('rule.isOverride'))->toBeFalse();
        expect(NumberingRule::query()->where('tenant_id', $admin->tenant_id)->whereNull('doc_type_id')->count())->toBe(1);
        expect(AuditLog::query()->where('event', 'dcs.numbering_rule.saved')->exists())->toBeTrue();
    });

    it('creates an override for a type with the default scope tokens, then updates it in place', function () {
        $admin = numberingAdmin();
        $sop = typeId($admin, 'SOP');

        $first = dcsAs($admin)->putJson('/api/dcs/numbering-rule', ['mask' => 'SOP/{Month}/{Sequence}', 'docTypeId' => $sop])->assertOk()->json('rule');
        $second = dcsAs($admin)->putJson('/api/dcs/numbering-rule', ['mask' => 'SOP-{Sequence:3}', 'docTypeId' => $sop])->assertOk()->json('rule');

        expect($first['isOverride'])->toBeTrue()
            ->and($first['scopeTokens'])->toBe(['DocType', 'Department', 'Year'])
            ->and($second['id'])->toBe($first['id'])
            ->and($second['mask'])->toBe('SOP-{Sequence:3}');
        expect(NumberingRule::query()->where('tenant_id', $admin->tenant_id)->whereNotNull('doc_type_id')->count())->toBe(1);
        dcsAs($admin)->getJson("/api/dcs/numbering-rule?docTypeId={$sop}")->assertJsonPath('isOverride', true)->assertJsonPath('mask', 'SOP-{Sequence:3}');
        dcsAs($admin)->getJson('/api/dcs/numbering-rule')->assertJsonPath('isOverride', false);
    });

    it('rejects an unusable mask with the Node sentence', function (?string $mask, string $message) {
        dcsAs(numberingAdmin())->putJson('/api/dcs/numbering-rule', ['mask' => $mask])
            ->assertStatus(400)->assertJsonPath('error', $message);
    })->with([
        'missing' => [null, 'Format nomor tidak boleh kosong.'],
        'blank' => ['   ', 'Format nomor tidak boleh kosong.'],
        'no sequence' => ['{DocType}/{Year}', 'Format nomor wajib memuat token {Sequence} atau {Sequence:N}.'],
        'unknown token' => ['{Nope}/{Sequence}', 'Token "{Nope}" tidak dikenal. Token yang tersedia: {DocType}, {Department}, {Year}, {Month}, {MonthRoman}, {Day}, {Sequence}.'],
    ]);

    it('answers 404 for a type of another tenant', function () {
        $foreign = DocumentType::factory()->create();

        dcsAs(numberingAdmin())->putJson('/api/dcs/numbering-rule', ['mask' => '{Sequence}', 'docTypeId' => $foreign->uuid])->assertNotFound();
        expect(NumberingRule::query()->where('doc_type_id', $foreign->id)->exists())->toBeFalse();
    });

    it('forbids users without dcs.config', function () {
        dcsAs(User::factory()->role(SystemRole::Viewer)->create())->putJson('/api/dcs/numbering-rule', ['mask' => '{Sequence}'])->assertForbidden();
    });
});

describe('destroy', function () {
    it('removes the override and returns the default rule', function () {
        $admin = numberingAdmin();
        $sop = typeId($admin, 'SOP');
        dcsAs($admin)->putJson('/api/dcs/numbering-rule', ['mask' => 'SOP-{Sequence}', 'docTypeId' => $sop])->assertOk();

        $response = dcsAs($admin)->deleteJson("/api/dcs/numbering-rule?docTypeId={$sop}")->assertOk();

        expect($response->json('success'))->toBeTrue()
            ->and($response->json('rule.isOverride'))->toBeFalse()
            ->and($response->json('rule.mask'))->toBe('{DocType}/{Department}/{Year}/{Sequence:4}');
        expect(AuditLog::query()->where('event', 'dcs.numbering_rule.override_removed')->exists())->toBeTrue();
    });

    it('is a no-op without an override and never removes the default rule', function () {
        $admin = numberingAdmin();
        $sop = typeId($admin, 'SOP');

        dcsAs($admin)->deleteJson("/api/dcs/numbering-rule?docTypeId={$sop}")->assertOk()->assertJsonPath('rule.isOverride', false);

        expect(NumberingRule::query()->where('tenant_id', $admin->tenant_id)->whereNull('doc_type_id')->count())->toBe(1);
    });

    it('requires a docTypeId', function () {
        dcsAs(numberingAdmin())->deleteJson('/api/dcs/numbering-rule')
            ->assertStatus(400)->assertExactJson(['error' => 'docTypeId wajib diisi untuk mengembalikan ke format default.']);
    });

    it('cannot remove an override of another tenant', function () {
        $foreign = DocumentType::factory()->create();
        $rule = NumberingRule::factory()->for($foreign->tenant)->create(['doc_type_id' => $foreign->id, 'mask' => '{Sequence}']);

        dcsAs(numberingAdmin())->deleteJson("/api/dcs/numbering-rule?docTypeId={$foreign->uuid}")->assertOk();

        expect($rule->fresh())->not->toBeNull();
    });
});
