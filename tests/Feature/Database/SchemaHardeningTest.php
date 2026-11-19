<?php

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\BodyStyle;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\ReviewCommentStatus;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The constraints exist only on PostgreSQL.');
    }
});

it('stores every moment as timestamptz', function () {
    $columns = DB::select(
        "select table_name, column_name from information_schema.columns where table_schema = current_schema() and data_type = 'timestamp without time zone' and (table_name like 'dcs\\_%' or table_name in ('users', 'tenants', 'roles', 'permissions', 'role_permission', 'password_reset_tokens', 'failed_jobs'))"
    );

    expect(collect($columns)->map(fn ($c) => "{$c->table_name}.{$c->column_name}")->all())->toBe([]);
});

it('refuses a version status outside the enum', function () {
    $version = DocumentVersion::factory()->create();

    expect(fn () => DB::table('dcs_document_versions')->where('id', $version->id)->update(['status' => 'published']))
        ->toThrow(QueryException::class, 'dcs_document_versions_status_check');
});

it('refuses an unknown body style on a document type', function () {
    $type = DocumentType::factory()->create();

    expect(fn () => DB::table('dcs_document_types')->where('id', $type->id)->update(['body_style' => 'fancy']))
        ->toThrow(QueryException::class, 'dcs_document_types_body_style_check');
});

it('refuses a jsonb value of the wrong top-level shape', function () {
    $type = DocumentType::factory()->create();

    expect(fn () => DB::table('dcs_document_types')->where('id', $type->id)->update(['default_lanes' => '{"a":1}']))
        ->toThrow(QueryException::class, 'dcs_document_types_default_lanes_json_type');
});

it('keeps a moment on the same instant when converted and when reverted', function () {
    $migration = require database_path('migrations/2026_11_19_090000_harden_timestamps_and_enums.php');
    $document = Document::factory()->create();
    $read = fn () => DB::table('dcs_documents')->where('id', $document->id)->value(DB::raw("to_char(created_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI')"));

    $migration->down();
    DB::table('dcs_documents')->where('id', $document->id)->update(['created_at' => '2026-03-01 10:00:00']);
    $migration->up();

    expect($read())->toBe('2026-03-01 10:00');

    $migration->down();
    expect($read())->toBe('2026-03-01 10:00');
    $migration->up();
});

it('keeps every CHECK list equal to the cases of its PHP enum', function () {
    $checks = [
        ['dcs_document_versions', 'status', VersionStatus::class],
        ['dcs_document_events', 'actor_type', ActorType::class],
        ['dcs_renditions', 'kind', RenditionKind::class],
        ['dcs_review_comments', 'status', ReviewCommentStatus::class],
        ['dcs_read_receipts', 'status', ReceiptStatus::class],
        ['dcs_document_types', 'layout_style', LayoutStyle::class],
        ['dcs_document_types', 'header_style', HeaderStyle::class],
        ['dcs_document_types', 'body_style', BodyStyle::class],
    ];

    foreach ($checks as [$table, $column, $enum]) {
        $definition = DB::scalar('select pg_get_constraintdef(oid) from pg_constraint where conname = ?', ["{$table}_{$column}_check"]);
        preg_match_all("/'([a-z_]+)'::character varying/", $definition, $found);

        expect($found[1])->toEqualCanonicalizing(array_column($enum::cases(), 'value'), "{$table}.{$column}");
    }
});

it('keeps microseconds after conversion and rounds to whole seconds when reverted', function () {
    $migration = require database_path('migrations/2026_11_19_090000_harden_timestamps_and_enums.php');
    $document = Document::factory()->create();
    $read = fn () => DB::table('dcs_documents')->where('id', $document->id)->value(DB::raw("to_char(created_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS.US')"));

    DB::table('dcs_documents')->where('id', $document->id)->update(['created_at' => '2026-03-01 10:00:00.654321+00']);
    expect($read())->toBe('2026-03-01 10:00:00.654321');

    $migration->down();
    expect($read())->toBe('2026-03-01 10:00:01.000000');
    $migration->up();
});

it('accepts an empty object written as [] but refuses a scalar or a list with items', function () {
    $type = DocumentType::factory()->create();
    $set = fn (string $json) => DB::table('dcs_document_types')->where('id', $type->id)->update(['header_layout' => $json]);

    expect($set('[]'))->toBe(1)
        ->and($set('{"field":"scope"}'))->toBe(1)
        ->and(fn () => DB::transaction(fn () => $set('"text"')))->toThrow(QueryException::class, 'dcs_document_types_header_layout_json_type')
        ->and(fn () => DB::transaction(fn () => $set('[1]')))->toThrow(QueryException::class, 'dcs_document_types_header_layout_json_type');
});
