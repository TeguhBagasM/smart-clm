<?php

namespace App\Http\Requests\Dcs;

use App\Http\Requests\Dcs\Concerns\DraftContent;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A partial save of a draft from the wizard: plain JSON with any subset of the
 * content, plus the revision the client last saw (0 when nothing was saved yet).
 */
class SaveDraftRequest extends FormRequest
{
    use DraftContent;

    public function authorize(): bool|Response
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            ...$this->contentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['revision.required' => 'Sertakan revision draft yang terakhir Anda lihat (0 bila belum pernah disimpan).'];
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return $this->contentData();
    }

    public function revision(): int
    {
        return $this->integer('revision');
    }
}
