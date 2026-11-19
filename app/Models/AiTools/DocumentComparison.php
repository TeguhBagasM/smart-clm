<?php

namespace App\Models\AiTools;

use App\Enums\AiTools\ComparisonStage;
use App\Enums\AiTools\ComparisonStatus;
use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\DocumentComparisonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A request to compare two documents article by article, with its result. Tenant owned;
 * `status`/`stage`/`progress` follow the queued pipeline, `summary` is the strategic review.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $created_by_id
 * @property ComparisonStatus $status
 * @property ComparisonStage|null $stage
 * @property int $progress
 * @property string $label_a
 * @property string $label_b
 * @property array<string, mixed>|null $summary
 * @property list<string>|null $warnings
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class DocumentComparison extends Model
{
    /** @use HasFactory<DocumentComparisonFactory> */
    use HasFactory;

    use HasUuid;

    protected $table = 'document_comparisons';

    protected $guarded = [];

    protected static function newFactory(): DocumentComparisonFactory
    {
        return DocumentComparisonFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<ComparisonSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(ComparisonSource::class, 'comparison_id');
    }

    /**
     * @return HasMany<ComparisonArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(ComparisonArticle::class, 'comparison_id');
    }

    /**
     * @return HasMany<ComparisonRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ComparisonRow::class, 'comparison_id');
    }

    public function enter(ComparisonStage $stage): void
    {
        $this->forceFill([
            'status' => ComparisonStatus::Processing,
            'stage' => $stage,
            'started_at' => $this->started_at ?? now(),
        ])->save();
    }

    public function leave(ComparisonStage $stage): void
    {
        $this->forceFill(['progress' => $stage->progressAfter()])->save();
    }

    public function warn(string $message): void
    {
        $this->forceFill(['warnings' => [...($this->warnings ?? []), $message]])->save();
    }

    public function source(string $side): ComparisonSource
    {
        return $this->sources()->where('side', $side)->firstOrFail();
    }

    protected function casts(): array
    {
        return [
            'status' => ComparisonStatus::class,
            'stage' => ComparisonStage::class,
            'summary' => 'array',
            'warnings' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'progress' => 'integer',
        ];
    }
}
