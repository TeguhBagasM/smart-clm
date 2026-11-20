<?php

use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Employee;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Runs a statement expected to fail inside a savepoint, so PostgreSQL keeps the
 * test transaction usable after the error.
 */
function savepoint(callable $statement): Closure
{
    return fn () => DB::transaction(fn () => $statement());
}

it('keeps a code unique per company regardless of case, but not across companies', function () {
    $tenant = Tenant::factory()->create();
    ContractCategory::factory()->for($tenant)->create(['code' => 'NDA']);

    expect(savepoint(fn () => ContractCategory::factory()->for($tenant)->create(['code' => 'nda'])))->toThrow(UniqueConstraintViolationException::class);

    ContractCategory::factory()->create(['code' => 'NDA']);
    expect(ContractCategory::query()->where('code', 'NDA')->count())->toBe(2);
});

it('keeps a name unique per company regardless of case', function () {
    $tenant = Tenant::factory()->create();
    ContractDocumentType::factory()->for($tenant)->create(['name' => 'Berita Acara']);

    expect(savepoint(fn () => ContractDocumentType::factory()->for($tenant)->create(['name' => 'BERITA ACARA'])))->toThrow(UniqueConstraintViolationException::class);
});

it('allows one employee category and one employee party type per company', function () {
    $tenant = Tenant::factory()->create();
    ContractCategory::factory()->for($tenant)->create(['is_employee_key' => true]);
    PartyType::factory()->for($tenant)->create(['is_employee' => true]);

    expect(savepoint(fn () => ContractCategory::factory()->for($tenant)->create(['is_employee_key' => true])))->toThrow(UniqueConstraintViolationException::class)
        ->and(savepoint(fn () => PartyType::factory()->for($tenant)->create(['is_employee' => true])))->toThrow(UniqueConstraintViolationException::class);
});

it('keeps a partner name unique inside its group only', function () {
    $group = PartnerGroup::factory()->create();
    Partner::factory()->for($group, 'group')->create(['name' => 'Cititrans']);

    expect(savepoint(fn () => Partner::factory()->for($group, 'group')->create(['name' => 'CITITRANS'])))->toThrow(UniqueConstraintViolationException::class);

    Partner::factory()->for(PartnerGroup::factory()->for($group->tenant), 'group')->create(['name' => 'Cititrans']);
    expect(Partner::query()->where('name', 'Cititrans')->count())->toBe(2);
});

it('links a user account to at most one employee and an employee number once per company', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    Employee::factory()->for($tenant)->create(['user_id' => $user->id, 'employee_number' => 'E-1']);

    expect(savepoint(fn () => Employee::factory()->create(['user_id' => $user->id])))->toThrow(UniqueConstraintViolationException::class)
        ->and(savepoint(fn () => Employee::factory()->for($tenant)->create(['employee_number' => 'e-1'])))->toThrow(UniqueConstraintViolationException::class);

    Employee::factory()->for($tenant)->count(2)->create(['employee_number' => null]);
    expect($tenant->employees()->count())->toBe(3);
});

it('refuses an unknown module and a non-positive retention on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The CHECK constraints exist only on PostgreSQL.');
    }

    $type = ContractDocumentType::factory()->create();

    expect(savepoint(fn () => DB::table('contract_document_types')->where('id', $type->id)->update(['module' => 'internal'])))
        ->toThrow(QueryException::class, 'contract_document_types_module_check')
        ->and(savepoint(fn () => DB::table('contract_document_types')->where('id', $type->id)->update(['retention_years' => 0])))
        ->toThrow(QueryException::class, 'contract_document_types_retention_check');
});

it('stores every moment of the master data tables as timestamptz', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Column types are checked on PostgreSQL.');
    }

    $tables = ['tenant_platforms', 'contract_categories', 'contract_document_types', 'contract_document_type_codes', 'employment_types', 'party_types', 'currencies', 'partner_groups', 'partners', 'employees'];
    $columns = DB::table('information_schema.columns')->where('table_schema', DB::raw('current_schema()'))
        ->whereIn('table_name', $tables)->where('data_type', 'timestamp without time zone')->pluck('column_name');

    expect($columns->all())->toBe([]);
});
