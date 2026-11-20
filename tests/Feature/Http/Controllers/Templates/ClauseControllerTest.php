<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $this->category = ContractCategory::factory()->for($this->tenant)->create();
    $this->payload = fn (array $over = []) => array_merge(['title' => 'Kerahasiaan', 'body' => 'Para pihak menjaga kerahasiaan.', 'category_id' => $this->category->id], $over);
});

it('sends a guest to log in', function () {
    $this->get(route('contract-library.clauses.index'))->assertRedirect(route('login'));
});

it('shows the own company clauses with their usage and rights to a legal user', function () {
    ContractClause::factory()->for($this->tenant)->create(['title' => 'Force Majeure']);
    ContractClause::factory()->for($this->other)->create(['title' => 'Milik Lain']);

    $this->actingAs($this->legal)->get(route('contract-library.clauses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contract-library/Clauses')
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Force Majeure')
            ->where('items.data.0.usage_count', 0)
            ->where('items.data.0.can', ['update' => true, 'deactivate' => true])
            ->where('company.id', $this->tenant->id)
            ->where('companies', null));
});

it('gives a company user without the right the friendly 403 page, and refuses writes', function () {
    $clause = ContractClause::factory()->for($this->tenant)->create();

    $this->actingAs($this->hr)->get(route('contract-library.clauses.index'))->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('errors/Forbidden'));
    $this->actingAs($this->hr)->post(route('contract-library.clauses.store'), ($this->payload)())->assertForbidden();
    $this->actingAs($this->hr)->put(route('contract-library.clauses.update', $clause), ($this->payload)(['title' => 'X']))->assertForbidden();
    $this->actingAs($this->hr)->patch(route('contract-library.clauses.deactivate', $clause))->assertForbidden();

    expect($clause->refresh()->title)->not->toBe('X')->and($clause->is_active)->toBeTrue();
});

it('answers 404 for a clause of another company', function () {
    $foreign = ContractClause::factory()->for($this->other)->create();

    $this->actingAs($this->legal)->put(route('contract-library.clauses.update', $foreign), ($this->payload)(['title' => 'X']))->assertNotFound();
    $this->actingAs($this->legal)->patch(route('contract-library.clauses.deactivate', $foreign))->assertNotFound();
    expect($foreign->refresh()->is_active)->toBeTrue();
});

it('adds a clause with a toast and an audit entry', function () {
    $this->actingAs($this->legal)->post(route('contract-library.clauses.store'), ($this->payload)())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pasal "Kerahasiaan" ditambahkan.']);

    expect(ContractClause::query()->where('tenant_id', $this->tenant->id)->value('title'))->toBe('Kerahasiaan')
        ->and(AuditLog::query()->where('event', 'contract_templates.clauses.created')->count())->toBe(1);
});

it('says what to fix when the body holds HTML, a title is taken, or a placeholder is unknown', function () {
    $this->actingAs($this->legal)->post(route('contract-library.clauses.store'), ($this->payload)(['body' => 'Teks <b>tebal</b>']))
        ->assertSessionHasErrors(['body' => config('contract_templates.messages.body_html')]);

    $this->actingAs($this->legal)->post(route('contract-library.clauses.store'), ($this->payload)());
    $this->actingAs($this->legal)->post(route('contract-library.clauses.store'), ($this->payload)(['title' => 'kerahasiaan']))
        ->assertSessionHasErrors(['title' => config('contract_templates.messages.title_taken')]);

    $this->actingAs($this->legal)->post(route('contract-library.clauses.store'), ($this->payload)(['title' => 'Lain', 'body' => 'Hubungi {{Tidak}}.']))
        ->assertSessionHasErrors('body');

    expect(ContractClause::query()->count())->toBe(1);
});

it('updates a clause, raising its revision when the content changes', function () {
    $clause = ContractClause::factory()->for($this->tenant)->create(['title' => 'Kerahasiaan', 'body' => 'Lama.', 'contract_category_id' => $this->category->id]);

    $this->actingAs($this->legal)->put(route('contract-library.clauses.update', $clause), ($this->payload)(['body' => 'Baru.']))
        ->assertSessionHasNoErrors()->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pasal "Kerahasiaan" disimpan.']);

    expect($clause->refresh()->revision)->toBe(2);
});

it('hides and shows a clause, with the usage named in the toast', function () {
    $clause = ContractClause::factory()->for($this->tenant)->create(['title' => 'Kerahasiaan']);

    $this->actingAs($this->legal)->patch(route('contract-library.clauses.deactivate', $clause))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pasal "Kerahasiaan" disembunyikan dari pilihan baru. Masih disalin oleh 0 templat, dan templat itu tetap memakainya.']);
    expect($clause->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->legal)->patch(route('contract-library.clauses.reactivate', $clause))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pasal "Kerahasiaan" ditampilkan lagi.']);
    expect($clause->refresh()->is_active)->toBeTrue();
});
