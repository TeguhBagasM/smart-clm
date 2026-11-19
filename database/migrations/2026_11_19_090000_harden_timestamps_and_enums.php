<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables whose `timestamps()` columns were created as `timestamp without
     * time zone`. Convention: every moment is a `timestamptz`.
     *
     * @var array<int, string>
     */
    private array $timestampTables = [
        'users', 'tenants', 'roles', 'permissions', 'role_permission', 'password_reset_tokens', 'failed_jobs',
        'dcs_departments', 'dcs_document_types', 'dcs_clauses', 'dcs_numbering_rules',
        'dcs_number_sequences', 'dcs_number_allocations', 'dcs_documents', 'dcs_document_versions',
        'dcs_version_contents', 'dcs_tenant_settings',
    ];

    /**
     * Values a code branch depends on, as plain strings guarded by a CHECK.
     * Adding a case to the PHP enum needs a migration that widens its CHECK.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $enumChecks = [
        'dcs_document_versions' => ['status' => ['draft', 'under_review', 'approved', 'effective', 'superseded']],
        'dcs_document_events' => ['actor_type' => ['user', 'guest', 'system'], 'source' => ['live', 'import']],
        'dcs_renditions' => ['kind' => ['composed', 'uploaded', 'signed', 'translated']],
        'dcs_review_comments' => ['status' => ['open', 'resolved']],
        'dcs_read_receipts' => ['status' => ['pending', 'read', 'acknowledged']],
        'dcs_version_contents' => [
            'language_mode' => ['single', 'multi'],
            'flow_mode' => ['none', 'builder', 'image'],
            'flow_display_style' => ['diagram', 'table'],
        ],
        'dcs_document_types' => [
            'layout_style' => ['standard', 'memo', 'kebijakan'],
            'header_style' => ['iso', 'simple'],
            'body_style' => ['standard', 'formal'],
        ],
    ];

    /**
     * jsonb columns whose top-level shape is fixed (array or object).
     * The shape of what is inside is validated by the FormRequests/Actions.
     *
     * @var array<string, array<string, string>>
     */
    private array $jsonShapes = [
        'dcs_clauses' => ['tags' => 'array'],
        'dcs_document_types' => ['default_lanes' => 'array', 'header_layout' => 'object_or_empty'],
        'dcs_numbering_rules' => ['scope_tokens' => 'array'],
        'dcs_version_contents' => ['flow_lanes' => 'array', 'flow_steps' => 'array', 'render_layout' => 'object_or_empty'],
        'dcs_version_sections' => ['table_data' => 'object'],
        // Laravel encodes an empty array as `[]`, so an empty object is accepted in either form.
        'dcs_review_comments' => ['anchor' => 'object_or_empty'],
        'dcs_number_sequences' => ['scope_values' => 'object_or_empty'],
        'dcs_document_events' => ['payload' => 'object_or_empty'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Values were written in UTC (database.connections.pgsql.timezone), so they are read back as UTC.
        $this->convertTimestamps('timestamptz', 'UTC');

        foreach ($this->enumChecks as $table => $columns) {
            foreach ($columns as $column => $values) {
                $list = implode(', ', array_map(fn (string $value): string => "'{$value}'", $values));
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$list}))");
            }
        }

        foreach ($this->jsonShapes as $table => $columns) {
            foreach ($columns as $column => $type) {
                $shape = $type === 'object_or_empty'
                    ? "jsonb_typeof({$column}) = 'object' OR {$column} = '[]'::jsonb"
                    : "jsonb_typeof({$column}) = '{$type}'";
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_json_type CHECK ({$column} IS NULL OR {$shape})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->jsonShapes as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_json_type");
            }
        }

        foreach ($this->enumChecks as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
            }
        }

        $this->convertTimestamps('timestamp(0) without time zone', 'UTC', onlyUp: false);
    }

    private function convertTimestamps(string $type, string $zone, bool $onlyUp = true): void
    {
        $from = $type === 'timestamptz' ? 'timestamp without time zone' : 'timestamp with time zone';

        // Reverting touches only the columns this migration converted, never ones that were always timestamptz.
        $only = $onlyUp ? '' : " and column_name in ('created_at', 'updated_at', 'email_verified_at', 'last_login_at', 'two_factor_confirmed_at', 'failed_at')";

        foreach ($this->timestampTables as $table) {
            $columns = DB::select(
                'select column_name from information_schema.columns where table_schema = current_schema() and table_name = ? and data_type = ?'.$only,
                [$table, $from],
            );

            foreach ($columns as $column) {
                $name = $column->column_name;
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$name} TYPE {$type} USING {$name} AT TIME ZONE '{$zone}'");
            }
        }
    }
};
