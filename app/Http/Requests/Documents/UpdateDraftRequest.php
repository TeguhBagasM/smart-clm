<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Dcs\SaveDraftRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * An autosave from the wizard: any subset of the content, the revision the
 * page last saw, and optionally the flow diagram image.
 */
class UpdateDraftRequest extends SaveDraftRequest
{
    public function authorize(): bool|Response
    {
        return Gate::inspect('view', $this->route('document'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'flowImage' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:10240']];
    }

    public function flowImage(): ?UploadedFile
    {
        $file = $this->file('flowImage');

        return $file instanceof UploadedFile ? $file : null;
    }
}
