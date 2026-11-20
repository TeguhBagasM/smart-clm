<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Templates\CountsUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Hides a clause, a field or a template from new choices, or shows it again.
 * Nothing is deleted: what already uses it keeps it. Returns how many records
 * use it, so the screen can say so, and whether anything changed.
 */
class SetTemplateRecordActive
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  'clauses'|'variables'|'templates'  $kind
     * @return array{usage: int, changed: bool}
     */
    public function handle(User $actor, Model&CountsUsage $record, bool $active, string $kind): array
    {
        $usage = $record->usageCount();

        if ((bool) $record->getAttribute('is_active') === $active) {
            return ['usage' => $usage, 'changed' => false];
        }

        DB::transaction(function () use ($actor, $record, $active, $kind): void {
            $before = $record->only(['is_active']);
            $record->forceFill(['is_active' => $active, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, "contract_templates.{$kind}.".($active ? 'reactivated' : 'deactivated'), $record, $before, $record->only(['is_active']), (int) $record->getAttribute('tenant_id'));
        });

        return ['usage' => $usage, 'changed' => true];
    }
}
