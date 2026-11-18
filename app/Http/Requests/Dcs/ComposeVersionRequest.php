<?php

namespace App\Http\Requests\Dcs;

use App\Http\Requests\Dcs\Concerns\DraftContent;
use App\Support\Dcs\DcsException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * "Susun": the multipart request of the Node API. The structured parts arrive as
 * JSON strings and are decoded first, with Node's messages for malformed JSON.
 * Every compose rebuilds the signature columns, so the signer fields always take
 * part in the save (empty when not sent).
 */
class ComposeVersionRequest extends FormRequest
{
    use DraftContent;

    /** @var array<string, string> field => message when its JSON is malformed */
    private const JSON_FIELDS = [
        'sections' => 'Format sections tidak valid',
        'flowLanes' => 'Format lane diagram alir tidak valid',
        'appendix' => 'Format lampiran tidak valid',
        'clauseIds' => 'Format daftar klausul tidak valid',
        'signatureAssignments' => 'Format penunjukan penandatangan tidak valid',
        'extraReviewers' => 'Format reviewer tambahan tidak valid',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = self::JSON_FIELDS;

        if ($this->input('flowMode') === 'builder') {
            $fields['flowSteps'] = 'Format diagram alir tidak valid';
        }

        foreach ($fields as $field => $message) {
            $raw = $this->input($field);

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw DcsException::badRequest($message);
            }

            $this->merge([$field => $decoded]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            'flowImage' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            'signatureAssignments' => [],
            'extraReviewers' => [],
            ...$this->contentData(),
        ];
    }

    public function flowImage(): ?UploadedFile
    {
        $file = $this->file('flowImage');

        return $file instanceof UploadedFile ? $file : null;
    }
}
