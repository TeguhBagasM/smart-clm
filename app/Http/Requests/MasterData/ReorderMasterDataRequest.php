<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The new order of a list: the ids in the order they should appear. The list
 * comes from the route parameter `list`.
 */
class ReorderMasterDataRequest extends SaveMasterDataRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('reorder', $this->list()->model());
    }

    protected function fieldRules(): array
    {
        return ['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['integer', 'distinct']];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids', [])));
    }
}
