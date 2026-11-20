<?php

namespace App\Models\Templates;

use App\Models\Templates\Concerns\BelongsToDraftVersion;
use Database\Factories\Templates\TemplateClauseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A clause of a template version: a copy of a library clause (or written for
 * this template only), in its place. Changes only while the version is a draft.
 *
 * @property int $id
 * @property int $contract_template_version_id
 * @property int $position
 * @property int|null $source_clause_id
 * @property int|null $source_revision
 * @property string $title
 * @property string $body
 * @property bool $is_mandatory
 * @property bool $is_protected
 */
class TemplateClause extends Model
{
    /** @use HasFactory<TemplateClauseFactory> */
    use BelongsToDraftVersion, HasFactory;

    public $timestamps = false;

    protected $table = 'contract_template_clauses';

    /**
     * The library clause this was copied from.
     *
     * @return BelongsTo<ContractClause, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContractClause::class, 'source_clause_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer', 'source_revision' => 'integer', 'is_mandatory' => 'boolean', 'is_protected' => 'boolean'];
    }
}
