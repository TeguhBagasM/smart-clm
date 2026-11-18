<?php

namespace App\Http\Resources\Documents\Config;

use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Support\Dcs\NumberingMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document type with the numbering it uses. `additional` carries the
 * company default rule and a sample department code for the example number.
 * Loads `numberingRules`.
 *
 * @mixin DocumentType
 */
class NumberingRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $own = $this->numberingRules->first(fn (NumberingRule $rule): bool => $rule->is_active);
        $default = $request->attributes->get('default_rule');
        $mask = $own instanceof NumberingRule ? $own->mask : ($default instanceof NumberingRule ? $default->mask : NumberingMask::DEFAULT_MASK);

        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'mask' => $mask,
            'custom' => $own instanceof NumberingRule,
            'example' => self::example($mask, $this->code, (string) $request->attributes->get('sample_department', 'DEPT')),
        ];
    }

    /**
     * What the first number of this year would look like.
     */
    public static function example(string $mask, string $typeCode, string $department): string
    {
        $now = now(config('dcs.numbering_timezone') ?? config('app.timezone'));

        return NumberingMask::render($mask, ['DocType' => $typeCode, 'Department' => $department, 'Year' => $now->year, 'Month' => $now->month, 'Day' => $now->day], 1);
    }
}
