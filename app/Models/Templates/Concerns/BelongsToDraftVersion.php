<?php

namespace App\Models\Templates\Concerns;

use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\TemplateVersion;
use App\Support\Templates\FrozenTemplateVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content rows of a template version (clauses, parties) may be added, changed
 * or removed only while the version is a draft. Mirrors the PostgreSQL trigger
 * contract_template_content_frozen, so the rule also holds on SQLite.
 *
 * @mixin Model
 */
trait BelongsToDraftVersion
{
    protected static function bootBelongsToDraftVersion(): void
    {
        static::saving(function (Model $row): void {
            self::ensureDraft($row->getAttribute('contract_template_version_id'));

            if ($row->exists && $row->isDirty('contract_template_version_id')) {
                self::ensureDraft($row->getOriginal('contract_template_version_id'));
            }
        });

        static::deleting(fn (Model $row) => self::ensureDraft($row->getOriginal('contract_template_version_id'), allowMissing: true));
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'contract_template_version_id');
    }

    private static function ensureDraft(mixed $versionId, bool $allowMissing = false): void
    {
        $status = TemplateVersion::query()->whereKey($versionId)->toBase()->value('status');

        if ($status === null && $allowMissing) {
            return;
        }

        if ($status !== TemplateVersionStatus::Draft->value) {
            throw FrozenTemplateVersion::version(is_scalar($versionId) ? (string) $versionId : null);
        }
    }
}
