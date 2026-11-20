<?php

namespace App\Support\MasterData;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The company check of a relation: every id a form sends for another list
 * must belong to the same company. The pivot tables carry no tenant_id, so
 * this is where "a category of company B on a type of company A" is refused.
 */
class OwnedIds
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     * @return list<int> the ids, without repeats
     */
    public function ensure(string $model, Tenant $tenant, array $ids, string $field, string $message): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids !== [] && $model::query()->where('tenant_id', $tenant->id)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $ids;
    }
}
