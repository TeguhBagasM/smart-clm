<?php

namespace App\Support\MasterData\Legacy;

use App\Enums\MasterData\MasterList;
use App\Models\Tenant;
use App\Support\MasterData\LookupCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Brings one Node list entry into the matching table, matched by the Node key
 * (legacy_key), so a second run changes nothing. A row the company already has
 * under the same name or code (a seeded default) is adopted: it only receives
 * the key. A row found by key is left as the company has it now.
 */
class LegacyLookupImporter
{
    /**
     * @param  array<string, mixed>  $attributes  set only when the row is created
     * @return array{0: Model|null, 1: string} the row and what happened (created|adopted|unchanged|skipped)
     */
    public function upsert(Tenant $tenant, MasterList $list, string $key, string $name, ?string $code, ImportReport $report, array $attributes = []): array
    {
        $name = trim($name);
        $model = $list->model();
        $ref = "{$list->value}:{$key}";

        $limit = in_array($list, [MasterList::Platform, MasterList::Currency], true) ? 100 : 150;

        if ($name === '' || mb_strlen($name) > $limit) {
            $report->error($ref, "nama kosong atau lebih dari {$limit} karakter");

            return $this->done($report, $list, null, 'skipped');
        }

        $rows = fn (): Builder => $model::query()->where('tenant_id', $tenant->id);
        $found = $rows()->where('legacy_key', $key)->first();

        if ($found !== null) {
            return $this->done($report, $list, $found, 'unchanged');
        }

        $code = $list->hasCode() ? $this->code($list, $name, $code, $rows) : null;
        $same = $rows()->where(function (Builder $query) use ($name, $code): void {
            $query->whereRaw('lower(name) = ?', [mb_strtolower($name)]);
            $code === null || $query->orWhereRaw('lower(code) = ?', [mb_strtolower($code)]);
        })->first();

        if ($same !== null && $same->getAttribute('legacy_key') === null) {
            $same->forceFill(['legacy_key' => $key])->save();

            return $this->done($report, $list, $same, 'adopted');
        }

        if ($same !== null) {
            $report->warn("{$ref}: \"{$name}\" ada dua kali di Node (beda huruf besar/kecil), yang kedua dilewati");

            return $this->done($report, $list, $same, 'skipped');
        }

        $row = new $model;
        $row->forceFill([
            'tenant_id' => $tenant->id, 'name' => $name, 'sort_order' => ((int) $rows()->max('sort_order')) + 1,
            'is_active' => true, 'legacy_key' => $key, ...($code === null ? [] : ['code' => $code, 'is_system' => false]), ...$attributes,
        ])->save();

        return $this->done($report, $list, $row, 'created');
    }

    /**
     * @return array{0: Model|null, 1: string}
     */
    private function done(ImportReport $report, MasterList $list, ?Model $row, string $outcome): array
    {
        $report->count($list->value, $outcome);

        return [$row, $outcome];
    }

    /**
     * The given code as is (a match with an existing code adopts that row), or one made from the
     * name, made unique inside the company with -2, -3, ...
     *
     * @param  \Closure(): Builder<Model>  $rows
     */
    private function code(MasterList $list, string $name, ?string $given, \Closure $rows): string
    {
        $max = $list->codeLength();
        $base = mb_strtoupper(mb_substr(trim((string) $given) !== '' ? trim((string) $given) : LookupCode::fromName($name, $max), 0, $max)) ?: 'X';
        $code = $base;

        for ($n = 2; trim((string) $given) === '' && $rows()->whereRaw('lower(code) = ?', [mb_strtolower($code)])->where('name', '!=', $name)->exists(); $n++) {
            $suffix = '-'.$n;
            $code = mb_substr($base, 0, $max - strlen($suffix)).$suffix;
        }

        return $code;
    }
}
