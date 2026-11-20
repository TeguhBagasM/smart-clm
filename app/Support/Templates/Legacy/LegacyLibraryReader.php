<?php

namespace App\Support\Templates\Legacy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Node library of one company (read only): clauses, templates and
 * fields. Node keeps each row as JSON in `data`; the id is its own column (for
 * fields, `key`). Fields without a tenantId belong to the default tenant.
 */
class LegacyLibraryReader
{
    private function db(): ConnectionInterface
    {
        return DB::connection(config()->string('master_data.import.connection'));
    }

    public function tenantExists(string $nodeId): bool
    {
        return $this->db()->table('tenants')->where('id', $nodeId)->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function clauses(string $nodeId): array
    {
        return $this->rows($this->db()->table('clauses')->where('data->tenantId', $nodeId), 'id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(string $nodeId): array
    {
        return $this->rows($this->db()->table('templates')->where('data->tenantId', $nodeId), 'id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function variables(string $nodeId): array
    {
        $query = $this->db()->table('variables');

        if ($nodeId === config()->string('contract_templates.import.legacy_default_tenant')) {
            return $this->rows($query->where(fn (Builder $inner) => $inner->where('data->tenantId', $nodeId)->orWhereNull('data->tenantId')), 'key');
        }

        return $this->rows($query->where('data->tenantId', $nodeId), 'key');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $query, string $keyColumn): array
    {
        $rows = [];

        foreach ($query->orderBy('seq')->get(["{$keyColumn} as id", 'data']) as $row) {
            $rows[] = ['id' => (string) $row->id] + $this->json($row->data);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
