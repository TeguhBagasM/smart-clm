<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\Clause;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Attaches a clause to the document types with the given codes (empty = usable
 * by all types). Unknown codes are refused instead of being dropped, because
 * dropping them could silently widen a restricted clause to all types.
 */
class SyncClauseDocTypes
{
    /**
     * @param  list<string>  $codes
     *
     * @throws ValidationException
     */
    public function handle(Tenant $tenant, Clause $clause, array $codes): void
    {
        $types = $tenant->dcsDocumentTypes()->whereIn('code', $codes)->pluck('id', 'code');
        $unknown = array_diff($codes, $types->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'applicableDocTypeCodes' => 'Kode jenis dokumen tidak dikenal: '.implode(', ', $unknown).'.',
            ]);
        }

        $clause->documentTypes()->sync($types->values()->all());
    }
}
