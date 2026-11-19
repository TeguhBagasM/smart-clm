<?php

namespace App\Http\Requests\Documents;

use App\Actions\Dcs\StoreHeaderAsset;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A letterhead logo: PNG, JPG or WebP, 2 MB at most. `mimes` looks at the file's
 * content, not only its extension; the action then decodes it for real.
 */
class StoreHeaderAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width='.StoreHeaderAsset::MAX_PIXELS.',max_height='.StoreHeaderAsset::MAX_PIXELS]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas logo.',
            'file.mimes' => 'Logo harus berformat PNG, JPG atau WebP.',
            'file.max' => 'Ukuran logo maksimal 2 MB.',
            'file.dimensions' => 'Dimensi logo maksimal '.StoreHeaderAsset::MAX_PIXELS.' x '.StoreHeaderAsset::MAX_PIXELS.' px.',
            'file.uploaded' => 'Berkas gagal diunggah.',
        ];
    }
}
