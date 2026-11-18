<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\Dcs\DocumentFactory;
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
