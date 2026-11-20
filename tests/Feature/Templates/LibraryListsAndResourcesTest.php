<?php

use App\Actions\Templates\PublishTemplateVersion;
use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveTemplateDraft;
use App\Enums\SystemRole;
use App\Http\Resources\Templates\ContractClauseResource;
use App\Http\Resources\Templates\ContractTemplateResource;
use App\Http\Resources\Templates\TemplateVersionResource;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Listing\ContractClauseListSpec;
use App\Support\Listing\ContractTemplateListSpec;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->category = ContractCategory::factory()->for($this->tenant)->create();
    $this->clauses = fn () => app(SaveContractClause::class);
});

it('searches, filters and orders the clause list, and only shows the company own rows', function () {
    ($this->clauses)()->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'Rahasia dagang.', 'category_id' => $this->category->id, 'is_mandatory' => true]);
    ($this->clauses)()->handle($this->legal, $this->tenant, ['title' => 'Force Majeure', 'body' => 'Bencana.', 'category_id' => $this->category->id]);
    ContractClause::factory()->create(['title' => 'Asing']);
    $spec = new ContractClauseListSpec([['value' => (string) $this->category->id, 'label' => 'Umum']]);
    $titles = fn (array $query) => $spec->apply(ContractClause::query()->where('tenant_id', $this->tenant->id), $spec->parse(Request::create('/', 'GET', $query)))->get()->pluck('title')->all();

    expect($titles([]))->toBe(['Force Majeure', 'Kerahasiaan'])
        ->and($titles(['search' => 'rahasia']))->toBe(['Kerahasiaan'])
        ->and($titles(['filter' => ['kind' => 'mandatory']]))->toBe(['Kerahasiaan'])
        ->and($titles(['sort' => 'title', 'direction' => 'desc']))->toBe(['Kerahasiaan', 'Force Majeure'])
        ->and($titles(['filter' => ['category' => (string) $this->category->id, 'status' => 'inactive']]))->toBe([]);
});

it('lists templates by whether a version is published', function () {
    $template = app(SaveContractTemplate::class)->handle($this->legal, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    app(SaveContractTemplate::class)->handle($this->legal, $this->tenant, ['name' => 'SLA', 'category_id' => $this->category->id]);
    $clause = ($this->clauses)()->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'x', 'category_id' => $this->category->id]);
    app(SaveTemplateDraft::class)->handle($this->legal, $template, ['expected_revision' => 1, 'parties' => ['Pihak Pertama', 'Pihak Kedua'], 'clause_ids' => [$clause->id], 'variables' => []]);
    app(PublishTemplateVersion::class)->handle($this->legal, $template, 2);

    $spec = new ContractTemplateListSpec([]);
    $names = fn (array $query) => $spec->apply(ContractTemplate::query()->where('tenant_id', $this->tenant->id), $spec->parse(Request::create('/', 'GET', $query)))->get()->pluck('name')->all();

    expect($names(['filter' => ['version' => 'published']]))->toBe(['NDA'])
        ->and($names(['filter' => ['version' => 'draft_only']]))->toBe(['SLA']);
});

it('tells a clause and a template what the person may do, and a version which copies changed upstream', function () {
    $clause = ($this->clauses)()->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'x', 'category_id' => $this->category->id]);
    $request = fn (User $user) => tap(Request::create('/'), fn (Request $r) => $r->setUserResolver(fn () => $user));

    expect((new ContractClauseResource($clause))->toArray($request($this->legal))['can'])->toBe(['update' => true, 'deactivate' => true])
        ->and((new ContractClauseResource($clause))->toArray($request(User::factory()->role(SystemRole::HrGa)->create()))['can'])->toBe(['update' => false, 'deactivate' => false]);

    $template = app(SaveContractTemplate::class)->handle($this->legal, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $draft = TemplateVersion::query()->where('contract_template_id', $template->id)->sole();
    $draft->clauses()->forceCreate(['position' => 1, 'source_clause_id' => $clause->id, 'source_revision' => 1, 'title' => 'Kerahasiaan', 'body' => 'x', 'is_mandatory' => false, 'is_protected' => false]);
    ($this->clauses)()->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'y', 'category_id' => $this->category->id], $clause);

    $version = $draft->refresh()->load(['clauses.source', 'parties', 'variables']);
    $payload = (new TemplateVersionResource($version))->toArray($request($this->legal));

    expect($payload['clauses'][0]['source_updated'])->toBeTrue()
        ->and($payload['status'])->toBe(['value' => 'draft', 'label' => 'Draf'])
        ->and($payload['parties'])->toBe([]);

    $templateDraftOnly = (new ContractTemplateResource($template->load(['currentVersion', 'draft'])))->toArray($request($this->legal))['can'];
    expect($templateDraftOnly)->toMatchArray(['update' => true, 'saveDraft' => true, 'publish' => true, 'startDraft' => false, 'duplicate' => true]);
});
