<?php

namespace App\Http\Requests\Documents;

use App\Support\Dcs\Header\HeaderPalette;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandColorsRequest extends FormRequest
{
    public const MAX_COLORS = 24;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'colors' => ['present', 'array', 'max:'.self::MAX_COLORS],
            'colors.*.hex' => ['required', 'string', 'regex:'.HeaderPalette::HEX_PATTERN],
            'colors.*.name' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return list<array{hex: string, name: string}>
     */
    public function colors(): array
    {
        $seen = [];

        foreach ((array) $this->input('colors') as $color) {
            $hex = HeaderPalette::canonical((string) $color['hex']);
            $seen[$hex] ??= ['hex' => $hex, 'name' => trim(strip_tags((string) ($color['name'] ?? '')))];
        }

        return array_values($seen);
    }
}
