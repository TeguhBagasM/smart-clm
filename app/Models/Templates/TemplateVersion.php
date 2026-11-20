<?php

namespace App\Models\Templates;

use App\Enums\Templates\TemplateVersionStatus;
use App\Support\Templates\FrozenTemplateVersion;
use Database\Factories\Templates\TemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One version of a contract template: its opening, closing and addendum
 * wording, and (through relations) its clauses, parties and fields. Once it
 * leaves draft it is frozen: only the move published → retired may still be
 * saved, and it cannot be deleted. Mirrors the PostgreSQL trigger
 * contract_template_versions_frozen.
 *
 * @property int $id
 * @property int $contract_template_id
 * @property int $version_no
 * @property TemplateVersionStatus $status
 * @property string|null $opening_paragraph
 * @property string|null $closing_paragraph
 * @property string|null $addendum_recital
 * @property int $draft_revision
 * @property Carbon|null $published_at
 * @property int|null $published_by_id
 * @property Carbon|null $retired_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory;

    /** What may still be saved on a frozen version (retiring it). */
    private const CHANGEABLE_WHEN_FROZEN = ['status', 'retired_at', 'updated_at', 'updated_by_id'];

    protected $table = 'contract_template_versions';

    protected static function booted(): void
    {
        static::updating(function (TemplateVersion $version): void {
            $was = TemplateVersionStatus::from((string) $version->getRawOriginal('status'));

            if ($was->isEditable()) {
                return;
            }

            $retiring = ! $version->isDirty('status') || $was->canTransitionTo($version->status);

            if (! $retiring || array_diff(array_keys($version->getDirty()), self::CHANGEABLE_WHEN_FROZEN) !== []) {
                throw FrozenTemplateVersion::version($version->getKey());
            }
        });

        static::deleting(function (TemplateVersion $version): void {
            if (! TemplateVersionStatus::from((string) $version->getRawOriginal('status'))->isEditable()) {
                throw FrozenTemplateVersion::version($version->getKey());
            }
        });
    }

    /**
     * @return BelongsTo<ContractTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    /**
     * @return HasMany<TemplateClause, $this>
     */
    public function clauses(): HasMany
    {
        return $this->hasMany(TemplateClause::class, 'contract_template_version_id')->orderBy('position');
    }

    /**
     * @return HasMany<TemplateParty, $this>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(TemplateParty::class, 'contract_template_version_id')->orderBy('position');
    }

    /**
     * The fields this version asks for, in order, each required or not.
     *
     * @return BelongsToMany<ContractVariable, $this>
     */
    public function variables(): BelongsToMany
    {
        return $this->belongsToMany(ContractVariable::class, 'contract_template_version_variables', 'contract_template_version_id', 'contract_variable_id')
            ->withPivot(['is_required', 'position'])->orderByPivot('position');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => TemplateVersionStatus::class, 'version_no' => 'integer', 'draft_revision' => 'integer', 'published_at' => 'datetime', 'retired_at' => 'datetime'];
    }
}
