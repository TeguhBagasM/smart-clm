<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\NumberingMask;
use Database\Factories\Dcs\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A controlled document. Its content lives in versions; the number is fixed when
 * the document is created.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $document_number
 * @property string|null $base_number
 * @property string|null $number_mask
 * @property array<string, string|int>|null $number_tokens
 * @property int $doc_type_id
 * @property int $department_id
 * @property string $title
 * @property int $owner_user_id
 * @property int|null $current_version_id
 * @property string|null $archive_category
 * @property string|null $archive_sub_folder_id
 * @property int $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DocumentType $type
 * @property-read Department $department
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuid;

    protected $table = 'dcs_documents';

    protected function casts(): array
    {
        return ['number_tokens' => 'array'];
    }

    /**
     * The full number of a revision, written with the pattern the document was numbered
     * with (a document numbered by hand, or before patterns carried a revision, keeps its number).
     */
    public function numberFor(int $revision): string
    {
        $tokens = $this->number_tokens;

        if ($this->number_mask === null || $tokens === null) {
            return $this->document_number;
        }

        return NumberingMask::render($this->number_mask, $tokens, (int) $tokens['Seq'], $revision);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'doc_type_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Versions, newest Terbitan and Revisi first.
     *
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('major')->orderByDesc('minor');
    }

    /**
     * The newest Terbitan and Revisi, whatever its status.
     *
     * @return HasOne<DocumentVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany(['major' => 'max', 'minor' => 'max']);
    }

    /**
     * The version in force, if any.
     *
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /**
     * Limit the query to the documents the viewer may see: their own tenant's,
     * or all of them for a super admin.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        if (! $viewer->isSuperAdmin()) {
            $query->where('tenant_id', $viewer->tenant_id);
        }
    }

    /**
     * @return HasMany<DocumentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class);
    }

    /**
     * @return HasOne<NumberAllocation, $this>
     */
    public function numberAllocation(): HasOne
    {
        return $this->hasOne(NumberAllocation::class);
    }
}
