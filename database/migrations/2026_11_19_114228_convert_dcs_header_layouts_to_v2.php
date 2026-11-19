<?php

use App\Support\Dcs\Header\HeaderLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Letterheads saved as version 1 (span in twelfths, colour names) become
     * version 2 with the same look: the names turn into the hex the engine drew.
     */
    public function up(): void
    {
        DB::table('dcs_document_types')->whereNotNull('header_layout')->orderBy('id')->each(function (object $row): void {
            $stored = json_decode((string) $row->header_layout, true);

            if (! is_array($stored) || ($stored['version'] ?? 1) >= HeaderLayout::VERSION) {
                return;
            }

            DB::table('dcs_document_types')->where('id', $row->id)->update(['header_layout' => json_encode(HeaderLayout::fromV1($stored))]);
        });
    }

    public function down(): void
    {
        // Version 2 is read by the application whatever its stored version; nothing to undo.
    }
};
