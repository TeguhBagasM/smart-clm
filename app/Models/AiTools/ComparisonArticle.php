<?php

namespace App\Models\AiTools;

use Illuminate\Database\Eloquent\Model;

/**
 * A piece of one document (a "Pasal", a chapter, the preamble) found by the splitter.
 *
 * @property int $id
 * @property int $comparison_id
 * @property string $side
 * @property int $ordinal
 * @property string|null $number_label
 * @property string|null $heading
 * @property string $body
 */
class ComparisonArticle extends Model
{
    public $timestamps = false;

    protected $table = 'document_comparison_articles';

    protected $guarded = [];

    /**
     * The id the model sees ("A3", "B12"); never the database key.
     */
    public function ref(): string
    {
        return strtoupper($this->side).$this->ordinal;
    }
}
