<?php

namespace App\Support\MasterData\Legacy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Node database (read only): the company's platforms, categories,
 * master data lists, employees and the values its contracts use. It selects
 * only the fields the import needs, so salaries and national ids are never
 * loaded, and it leaves out the demo rows the Node code seeds.
 */
class LegacyReader
{
    private function db(): ConnectionInterface
    {
        return DB::connection(config()->string('master_data.import.connection'));
    }

    /**
     * @return array{name: string|null, platforms: list<string>}|null null when Node has no such tenant
     */
    public function tenant(string $nodeId): ?array
    {
        $row = $this->db()->table('tenants')->where('id', $nodeId)->select('data->name as name', 'data->platforms as platforms')->first();

        return $row === null ? null : ['name' => $row->name, 'platforms' => $this->strings($this->json($row->platforms))];
    }

    /**
     * @return list<string>
     */
    public function categories(string $nodeId): array
    {
        return $this->db()->table('categories')->where('tenantId', $nodeId)->orderBy('sort_order')->pluck('name')->map(fn ($name): string => (string) $name)->all();
    }

    /**
     * The `masterData` part of the tenant's settings.
     *
     * @return array<string, mixed>
     */
    public function masterData(string $nodeId): array
    {
        $row = $this->db()->table('settings')->where('tenantId', $nodeId)->select('data->masterData as master_data')->first();

        return $row === null ? [] : $this->json($row->master_data);
    }

    /**
     * Employees without their salary or any national id: only the fields the directory keeps.
     *
     * @return list<array{id: string, name: string|null, position: string|null, department: string|null, startDate: string|null}>
     */
    public function employees(string $nodeId): array
    {
        return $this->db()->table('employees')->where('data->tenantId', $nodeId)
            ->whereNotIn('id', config()->array('master_data.import.demo_employee_ids'))
            ->select('id', 'data->name as name', 'data->position as position', 'data->department as department', 'data->startDate as startDate')
            ->orderBy('seq')->get()
            ->map(fn (object $row): array => ['id' => (string) $row->id, 'name' => $row->name, 'position' => $row->position, 'department' => $row->department, 'startDate' => $row->startDate])
            ->all();
    }

    /**
     * The values the tenant's contracts hold in each classification field and
     * how many contracts hold each (empty values and demo contracts left out).
     *
     * @return array<string, array<string, int>>
     */
    public function contractValues(string $nodeId): array
    {
        $demo = config()->array('master_data.import.demo_contract_numbers');
        $values = [];

        foreach (config()->array('master_data.import.contract_fields') as $field) {
            $rows = $this->db()->table('contracts')->where('data->tenantId', $nodeId)
                ->where(fn ($query) => $query->whereNull('data->contractNumber')->orWhereNotIn('data->contractNumber', $demo))
                ->select("data->{$field} as value")->selectRaw('count(*) as total')->groupBy('value')->get();

            foreach ($rows as $row) {
                if (trim((string) $row->value) !== '') {
                    $values[$field][trim((string) $row->value)] = (int) $row->total;
                }
            }
        }

        return $values;
    }

    /**
     * @return array<mixed>
     */
    private function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(array_map(fn (mixed $value): string => trim((string) $value), $values), fn (string $value): bool => $value !== ''));
    }
}
