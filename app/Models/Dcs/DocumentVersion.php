<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Database\Factories\Dcs\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One Terbitan (major) and Revisi (minor) of a document.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_id
 * @property string|null $number_label
 * @property int $major
 * @property int $minor
 * @property VersionStatus $status
 * @property string|null $change_summary
 * @property Carbon|null $effective_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $review_due_at
 * @property int $review_reminder_days
 * @property Carbon|null $last_review_reminder_on
 * @property Carbon|null $scheduled_effective_at
 * @property int $review_round
 * @property int $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VersionContent|null $content
 */
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory, HasUuid;

    protected $table = 'dcs_document_versions';

    /**
     * The version of a tenant with the given public id.
     *
     * @throws DcsException
     */
    public static function forTenant(Tenant $tenant, string $uuid): self
    {
        return Str::isUuid($uuid)
            ? self::query()->where('tenant_id', $tenant->id)->where('uuid', $uuid)->first() ?? throw DcsException::notFound('Versi tidak ditemukan')
            : throw DcsException::notFound('Versi tidak ditemukan');
    }

    /**
     * @return HasOne<VersionContent, $this>
     */
    public function content(): HasOne
    {
        return $this->hasOne(VersionContent::class, 'version_id');
    }

    /**
     * @return HasMany<VersionSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(VersionSection::class, 'version_id')->orderBy('position');
    }

    /**
     * @return HasMany<VersionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(VersionAttachment::class, 'version_id')->orderBy('position');
    }

    /**
     * @return HasMany<VersionStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(VersionStep::class, 'version_id')->orderBy('group_position')->orderBy('position');
    }

    /**
     * @return BelongsToMany<Clause, $this>
     */
    public function clauses(): BelongsToMany
    {
        return $this->belongsToMany(Clause::class, 'dcs_version_clauses', 'version_id', 'clause_id')->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return HasMany<Rendition, $this>
     */
    public function renditions(): HasMany
    {
        return $this->hasMany(Rendition::class, 'version_id')->orderBy('id');
    }

    /**
     * The newest main file (composed, uploaded or signed), or null before any exists.
     */
    public function currentRendition(): ?Rendition
    {
        return $this->renditions()->whereIn('kind', RenditionKind::mainValues())->reorder()->orderByDesc('id')->first();
    }

    /**
     * Every signature ever placed, including the ones a reset voided.
     *
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'version_id')->orderBy('step_order')->orderBy('id');
    }

    /**
     * The signatures that count: those placed since the last reset.
     *
     * @return HasMany<Approval, $this>
     */
    public function liveApprovals(): HasMany
    {
        return $this->approvals()->whereNull('voided_at');
    }

    /**
     * @return HasMany<ExternalReview, $this>
     */
    public function externalReviews(): HasMany
    {
        return $this->hasMany(ExternalReview::class, 'version_id');
    }

    /**
     * @return HasMany<ReviewComment, $this>
     */
    public function reviewComments(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'version_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * The newest file a person produced (composed or uploaded), ignoring the
     * signed copies stamped on top of it.
     */
    public function latestSourceRendition(): ?Rendition
    {
        return $this->renditions()->whereIn('kind', [RenditionKind::Composed->value, RenditionKind::Uploaded->value])->reorder()->orderByDesc('id')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'effective_at' => 'datetime',
            'superseded_at' => 'datetime',
            'review_due_at' => 'datetime',
            'scheduled_effective_at' => 'datetime',
            'last_review_reminder_on' => 'date',
        ];
    }
}
