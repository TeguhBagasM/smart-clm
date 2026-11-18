<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $version_id
 * @property int $position
 * @property string $heading
 * @property string $content
 * @property array{columns: list<string>, rows: list<list<string>>}|null $table_data
 */
class VersionSection extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_version_sections';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['table_data' => 'array'];
    }
}
