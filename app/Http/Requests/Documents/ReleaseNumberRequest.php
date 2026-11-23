<?php

namespace App\Http\Requests\Documents;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class ReleaseNumberRequest extends DocumentRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('releaseNumber', $this->route('document'));
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
