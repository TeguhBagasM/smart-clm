<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MasterData\MasterList;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puts rows of one company in the given order (sort_order 1, 2, 3, ...). Rows
 * not named keep their number, so name the whole visible list. Every id must
 * belong to the company; a foreign id refuses the whole change.
 */
class ReorderMasterRecords
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  list<int>  $orderedIds
     * @return int rows renumbered
     */
    public function handle(User $actor, Tenant $tenant, MasterList $list, array $orderedIds): int
    {
        $ids = array_values(array_unique($orderedIds));
        $model = $list->model();

        return DB::transaction(function () use ($actor, $tenant, $list, $ids, $model): int {
            $rows = $model::query()->where('tenant_id', $tenant->id)->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get();

            if ($rows->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'Ada pilihan yang bukan milik perusahaan ini atau sudah tidak ada. Muat ulang halaman lalu coba lagi.']);
            }

            foreach ($ids as $position => $id) {
                $model::query()->whereKey($id)->update(['sort_order' => $position + 1, 'updated_by_id' => $actor->id]);
            }

            $this->recordAuditLog->handle($actor, "master_data.{$list->value}.reordered", $tenant, ['order' => $rows->pluck('id')->all()], ['order' => $ids], $tenant->id);

            return count($ids);
        });
    }
}
