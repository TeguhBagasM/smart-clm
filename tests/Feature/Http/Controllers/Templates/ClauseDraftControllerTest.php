<?php

use App\Enums\SystemRole;
use App\Models\Templates\ContractClause;
use App\Models\Tenant;
use App\Models\User;
use Tests\Support\FakeLlmClient;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $this->fake = FakeLlmClient::install();
});

it('returns a suggested clause as JSON to a user with the right, and saves nothing', function () {
    $this->fake->reply(['title' => 'Kerahasiaan', 'body' => 'Para pihak menjaga kerahasiaan.', 'reasoning' => 'Sesuai permintaan.']);

    $this->actingAs($this->legal)->postJson(route('contract-library.clauses.draft'), ['description' => 'pasal kerahasiaan'])->assertOk()
        ->assertJsonPath('title', 'Kerahasiaan')->assertJsonPath('warnings', []);
    expect(ContractClause::query()->count())->toBe(0);
});

it('refuses a user without the right with 403, and a missing description with 422', function () {
    $this->actingAs($this->hr)->postJson(route('contract-library.clauses.draft'), ['description' => 'pasal'])->assertForbidden();
    $this->actingAs($this->legal)->postJson(route('contract-library.clauses.draft'), ['description' => ''])->assertUnprocessable()
        ->assertJsonValidationErrors('description');
    expect($this->fake->requests)->toBe([]);
});

it('limits how often a person can ask', function () {
    $this->fake->reply(['title' => 'Uji', 'body' => 'Isi.']);

    for ($i = 0; $i < 20; $i++) {
        $this->fake->reply(['title' => 'Uji', 'body' => 'Isi.']);
        $this->actingAs($this->legal)->postJson(route('contract-library.clauses.draft'), ['description' => 'pasal uji']);
    }

    $this->actingAs($this->legal)->postJson(route('contract-library.clauses.draft'), ['description' => 'pasal uji'])->assertStatus(429);
});
