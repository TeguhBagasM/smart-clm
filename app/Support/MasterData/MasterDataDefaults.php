<?php

namespace App\Support\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\Currency;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\PartyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Reads the default lists from config/master_data.php into rows ready to
 * insert. Only the shape lives here; the values live in the config file.
 */
class MasterDataDefaults
{
    /**
     * The lists whose rows are a code, a name and at most one flag.
     *
     * @return array<string, array{model: class-string<Model>, rows: list<array{code: string, name: string, flags: array<string, bool>}>}>
     */
    public function simpleLists(): array
    {
        $defaults = config('master_data.defaults');

        return [
            'contract_categories' => [
                'model' => ContractCategory::class,
                'rows' => $this->rows($defaults['contract_categories'], 'is_employee_key', $defaults['employee_category']),
            ],
            'employment_types' => ['model' => EmploymentType::class, 'rows' => $this->rows($defaults['employment_types'])],
            'party_types' => [
                'model' => PartyType::class,
                'rows' => $this->rows($defaults['party_types'], 'is_employee', $defaults['employee_party_type']),
            ],
            'currencies' => ['model' => Currency::class, 'rows' => $this->rows($defaults['currencies'], codeFromName: false)],
        ];
    }

    /**
     * @return list<array{code: string, name: string, module: DocumentModule, categories: list<string>}>
     */
    public function documentTypes(): array
    {
        $types = config()->array('master_data.defaults.contract_document_types');
        $rows = [];

        foreach (array_keys($types) as $index) {
            $rows[] = [
                'code' => Arr::string($types, "{$index}.code"),
                'name' => Arr::string($types, "{$index}.name"),
                'module' => DocumentModule::from(Arr::string($types, "{$index}.module")),
                'categories' => array_values(array_filter(Arr::array($types, "{$index}.categories"), is_string(...))),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $names
     * @return list<array{code: string, name: string, flags: array<string, bool>}>
     */
    private function rows(array $names, ?string $flag = null, ?string $flaggedName = null, bool $codeFromName = true): array
    {
        return array_map(fn (string $name): array => [
            'code' => $codeFromName ? LookupCode::fromName($name) : $name,
            'name' => $name,
            'flags' => $flag !== null && $name === $flaggedName ? [$flag => true] : [],
        ], $names);
    }
}
