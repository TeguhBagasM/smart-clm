<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;

/**
 * A default section (heading and starter text) of a document type.
 *
 * @property int $id
 * @property int $doc_type_id
 * @property int $position
 * @property string $heading
 * @property string $content
 */
class DocumentTypeSection extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_document_type_sections';
}
