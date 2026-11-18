<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use Database\Factories\Dcs\NumberingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document number mask. Each tenant has one default rule (doc_type_id null);
 * a document type may override it with its own mask.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $doc_type_id
 * @property string $name
 * @property string $mask
 * @property list<string> $scope_tokens
 * @property bool $is_active
 * @property-read Tenant $tenant
 */
class NumberingRule extends Model
{
    /** @use HasFactory<NumberingRuleFactory> */
    use HasFactory, HasUuid;

    protected $table = 'dcs_numbering_rules';

    /**
     * The rule that applies to a document type: its own override, else the tenant default.
     */
    public static function effectiveFor(Tenant $tenant, ?DocumentType $type): ?self
    {
        return self::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('doc_type_id')
                ->when($type !== null, fn ($query) => $query->orWhere('doc_type_id', $type?->id)))
            ->orderByRaw('doc_type_id is null')
            ->first();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope_tokens' => 'array', 'is_active' => 'boolean'];
    }
}
