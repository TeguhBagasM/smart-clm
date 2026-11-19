<?php

namespace App\Http\Requests\AiTools;

use App\Enums\AiTools\SourceKind;
use App\Models\AiTools\DocumentComparison;
use App\Models\Dcs\DocumentVersion;
use App\Support\Comparison\ComparisonSideInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Two sides, `a` and `b`, each one of: an uploaded PDF, a version of a controlled document of the
 * user's own company (`archive`), or pasted text. Files are checked by type and size here and by
 * content (the %PDF header) before they are stored.
 */
class StoreDocumentComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DocumentComparison::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (['a', 'b'] as $side) {
            $rules["{$side}.kind"] = ['required', Rule::in(['upload', 'archive', 'paste'])];
            $rules["{$side}.label"] = ['nullable', 'string', 'max:300'];
            $rules["{$side}.file"] = [
                "required_if:{$side}.kind,upload", 'nullable', 'file', 'mimes:pdf', 'max:'.config('ai.comparison.max_file_kb'),
                fn (string $attribute, mixed $value, Closure $fail) => $value instanceof UploadedFile && ! $this->looksLikePdf($value)
                    ? $fail('Berkas bukan PDF yang valid.') : null,
            ];
            $rules["{$side}.version"] = [
                "required_if:{$side}.kind,archive", 'nullable', 'uuid',
                Rule::exists('dcs_document_versions', 'uuid')->where('tenant_id', $this->user()->tenant_id),
            ];
            $rules["{$side}.text"] = ["required_if:{$side}.kind,paste", 'nullable', 'string', 'max:'.config('ai.comparison.max_paste_chars')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.kind.required' => 'Pilih sumber dokumen.',
            '*.file.required_if' => 'Unggah berkas PDF.',
            '*.file.mimes' => 'Berkas harus berupa PDF.',
            '*.file.max' => 'Ukuran berkas maksimal '.intdiv((int) config('ai.comparison.max_file_kb'), 1024).' MB.',
            '*.version.required_if' => 'Pilih dokumen dari arsip.',
            '*.version.exists' => 'Dokumen arsip tidak ditemukan.',
            '*.text.required_if' => 'Tempel isi dokumen.',
            '*.text.max' => 'Teks terlalu panjang.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $a = $this->input('a.version');

            if ($this->input('a.kind') === 'archive' && $a !== null && $a === $this->input('b.version')) {
                $validator->errors()->add('b.version', 'Pilih dua dokumen yang berbeda.');
            }
        }];
    }

    /**
     * @return array{ComparisonSideInput, ComparisonSideInput}
     */
    public function sides(): array
    {
        return [$this->side('a'), $this->side('b')];
    }

    private function side(string $side): ComparisonSideInput
    {
        $kind = match ($this->input("{$side}.kind")) {
            'upload' => SourceKind::Upload,
            'archive' => SourceKind::DcsVersion,
            default => SourceKind::Paste,
        };

        $file = $this->file("{$side}.file");

        return new ComparisonSideInput(
            kind: $kind,
            label: $this->input("{$side}.label"),
            file: $kind === SourceKind::Upload && $file instanceof UploadedFile ? $file : null,
            version: $kind === SourceKind::DcsVersion
                ? DocumentVersion::query()->with('document')->where('tenant_id', $this->user()->tenant_id)->where('uuid', $this->input("{$side}.version"))->firstOrFail()
                : null,
            text: $kind === SourceKind::Paste ? $this->input("{$side}.text") : null,
        );
    }

    private function looksLikePdf(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $head = $handle === false ? '' : (string) fread($handle, 1024);
        $handle !== false && fclose($handle);

        return str_contains($head, '%PDF-');
    }
}
