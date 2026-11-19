<?php

namespace App\Models\Dcs;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company branding and page margins printed into composed documents. Until the
 * settings module moves to Laravel these are imported from the Node app.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $company_name
 * @property string|null $company_address
 * @property string|null $company_logo_key
 * @property string|null $company_logo_mime
 * @property string|null $confidentiality_notice
 * @property list<array{hex: string, name: string}>|null $brand_colors
 * @property string|null $page_margin_top_mm
 * @property string|null $page_margin_right_mm
 * @property string|null $page_margin_bottom_mm
 * @property string|null $page_margin_left_mm
 */
class TenantSetting extends Model
{
    /** Points per millimetre, and the margin used when none is configured (as in Node). */
    private const MM_TO_PT = 2.834645669;

    private const DEFAULT_MARGIN_PT = 56.0;

    protected $table = 'dcs_tenant_settings';

    protected function casts(): array
    {
        return ['brand_colors' => 'array'];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Page margins in points. A missing or non-positive value falls back to 56 pt.
     *
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public function marginsInPoints(): array
    {
        $toPoints = fn (?string $mm): float => (float) $mm > 0 ? (float) $mm * self::MM_TO_PT : self::DEFAULT_MARGIN_PT;

        return [
            'top' => $toPoints($this->page_margin_top_mm),
            'right' => $toPoints($this->page_margin_right_mm),
            'bottom' => $toPoints($this->page_margin_bottom_mm),
            'left' => $toPoints($this->page_margin_left_mm),
        ];
    }
}
