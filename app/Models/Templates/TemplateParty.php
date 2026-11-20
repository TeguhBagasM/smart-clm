<?php

namespace App\Models\Templates;

use App\Models\Templates\Concerns\BelongsToDraftVersion;
use Illuminate\Database\Eloquent\Model;

/**
 * A party a template version names, e.g. "Pihak Pertama (Perusahaan)", in its
 * place. Changes only while the version is a draft.
 *
 * @property int $id
 * @property int $contract_template_version_id
 * @property int $position
 * @property string $label
 */
class TemplateParty extends Model
{
    use BelongsToDraftVersion;

    public $timestamps = false;

    protected $table = 'contract_template_parties';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
