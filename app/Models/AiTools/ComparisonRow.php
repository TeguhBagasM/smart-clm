<?php

namespace App\Models\AiTools;

use App\Enums\AiTools\ChangeKind;
use App\Enums\AiTools\Pairing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of the comparison matrix: the article of A, its counterpart in B (either may be missing),
 * the word-level diff computed in code and the model's explanation.
 *
 * @property int $id
 * @property int $comparison_id
 * @property int $ordinal
 * @property int|null $article_a_id
 * @property int|null $article_b_id
 * @property Pairing $pairing
 * @property ChangeKind $change_kind
 * @property array{a: list<array{t: string, o: string}>, b: list<array{t: string, o: string}>}|null $diff
 * @property string|null $explanation
 */
class ComparisonRow extends Model
{
    public $timestamps = false;

    protected $table = 'document_comparison_rows';

    protected $guarded = [];

    /**
     * @return BelongsTo<ComparisonArticle, $this>
     */
    public function articleA(): BelongsTo
    {
        return $this->belongsTo(ComparisonArticle::class, 'article_a_id');
    }

    /**
     * @return BelongsTo<ComparisonArticle, $this>
     */
    public function articleB(): BelongsTo
    {
        return $this->belongsTo(ComparisonArticle::class, 'article_b_id');
    }

    protected function casts(): array
    {
        return ['pairing' => Pairing::class, 'change_kind' => ChangeKind::class, 'diff' => 'array'];
    }
}
