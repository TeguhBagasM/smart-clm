<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\BodyStyle;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use Database\Factories\Dcs\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A class of controlled document (SOP, IK, ...) with its default sections,
 * signature steps and layout. `code` is the {DocType} token of document numbers.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property LayoutStyle $layout_style
 * @property HeaderStyle $header_style
 * @property BodyStyle $body_style
 * @property bool $letterhead
 * @property string $document_level
 * @property string $process_owner
 * @property list<string> $default_lanes
 * @property array<string, mixed>|null $header_layout
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, HasUuid;

    protected $table = 'dcs_document_types';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<DocumentTypeSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(DocumentTypeSection::class, 'doc_type_id')->orderBy('position');
    }

    /**
     * @return HasMany<DocumentTypeStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(DocumentTypeStep::class, 'doc_type_id')->orderBy('group_position')->orderBy('position');
    }

    /**
     * @return BelongsToMany<Clause, $this>
     */
    public function defaultClauses(): BelongsToMany
    {
        return $this->belongsToMany(Clause::class, 'dcs_document_type_default_clauses', 'doc_type_id', 'clause_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<NumberingRule, $this>
     */
    public function numberingRules(): HasMany
    {
        return $this->hasMany(NumberingRule::class, 'doc_type_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'layout_style' => LayoutStyle::class,
            'header_style' => HeaderStyle::class,
            'body_style' => BodyStyle::class,
            'letterhead' => 'boolean',
            'default_lanes' => 'array',
            'header_layout' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
