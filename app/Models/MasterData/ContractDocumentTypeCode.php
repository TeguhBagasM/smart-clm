<?php

namespace App\Models\MasterData;

use Database\Factories\MasterData\ContractDocumentTypeCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extra code segment of a document type, printed through {Codes}.
 *
 * @property int $id
 * @property int $document_type_id
 * @property string $label
 * @property string $value
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'value', 'sort_order'])]
class ContractDocumentTypeCode extends Model
{
    /** @use HasFactory<ContractDocumentTypeCodeFactory> */
    use HasFactory;

    protected $table = 'contract_document_type_codes';

    /**
     * @return BelongsTo<ContractDocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(ContractDocumentType::class, 'document_type_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
