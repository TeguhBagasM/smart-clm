<?php

use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveContractVariable;
use App\Actions\Templates\SaveTemplateDraft;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    $this->category = ContractCategory::factory()->for($this->tenant)->create();
    $this->field = fn (string $code, bool $active = true) => tap(app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => $code, 'name' => 'Kolom '.$code, 'value_type' => 'text']), function ($variable) use ($active) {
        if (! $active) {
            app(SetTemplateRecordActive::class)->handle($this->actor, $variable, false, 'variables');
        }
    });
    $this->clause = fn (string $body) => fn () => app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Uji '.md5($body), 'body' => $body, 'category_id' => $this->category->id]);
});

it('accepts a clause that names a field of the company and a system token', function () {
    ($this->field)('Alamat');

    $clause = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Domisili', 'body' => 'Di {{Alamat}}, {{Party1Name}} setuju.', 'category_id' => $this->category->id]);

    expect($clause->body)->toBe('Di {{Alamat}}, {{Party1Name}} setuju.');
});

it('refuses a clause that names an unknown or hidden field, and says which', function (string $body, string $code) {
    ($this->field)('Hidden', false);

    expect(fn () => ($this->clause)($body)())->toThrow(ValidationException::class);

    try {
        ($this->clause)($body)();
    } catch (ValidationException $exception) {
        expect(implode(' ', $exception->errors()['body']))->toContain($code);
    }
})->with([
    'unknown' => ['Isi {{Tidak}}.', '{{Tidak}}'],
    'hidden' => ['Isi {{Hidden}}.', '{{Hidden}}'],
]);

it('refuses a malformed placeholder in a clause, whatever it tries to run', function (string $body) {
    expect(fn () => ($this->clause)($body)())->toThrow(ValidationException::class);
})->with([
    'spaces' => ['Isi {{ Alamat }}.'],
    'expression' => ['Isi {{7*7}}.'],
    'call' => ['Isi {{system("ls")}}.'],
    'unclosed' => ['Isi {{Alamat.'],
    'nested' => ['Isi {{{{Alamat}}}}.'],
]);

it('keeps a draft to the fields it chose: a clause or paragraph naming another field is refused', function () {
    $alamat = ($this->field)('Alamat');
    $tanggal = ($this->field)('Tanggal');
    $clause = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Domisili', 'body' => 'Di {{Alamat}}.', 'category_id' => $this->category->id]);
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $save = fn (array $over) => app(SaveTemplateDraft::class)->handle($this->actor, $template, array_merge([
        'expected_revision' => 1, 'opening_paragraph' => null, 'closing_paragraph' => null, 'addendum_recital' => null,
        'parties' => ['Pihak Pertama', 'Pihak Kedua'], 'clause_ids' => [$clause->id], 'variables' => [['id' => $tanggal->id, 'required' => true]],
    ], $over));

    expect(fn () => $save([]))->toThrow(ValidationException::class);
    expect(fn () => $save(['opening_paragraph' => 'Pada {{Tanggal}} dan {{Alamat}}.']))->toThrow(ValidationException::class);

    $draft = $save(['variables' => [['id' => $tanggal->id, 'required' => true], ['id' => $alamat->id, 'required' => true]]]);
    expect($draft->draft_revision)->toBe(2);
});

it('refuses a paragraph with a malformed placeholder, even with no fields', function () {
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $clause = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Netral', 'body' => 'Teks biasa.', 'category_id' => $this->category->id]);

    expect(fn () => app(SaveTemplateDraft::class)->handle($this->actor, $template, [
        'expected_revision' => 1, 'opening_paragraph' => 'Pembuka {{ Nama }}', 'closing_paragraph' => null, 'addendum_recital' => null,
        'parties' => ['Pihak Pertama'], 'clause_ids' => [$clause->id], 'variables' => [],
    ]))->toThrow(ValidationException::class);
    expect(ContractTemplate::query()->findOrFail($template->id)->draft()->first()->draft_revision)->toBe(1);
});
