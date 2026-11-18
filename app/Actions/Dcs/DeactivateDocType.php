<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates a document type. Types are never deleted: documents keep pointing
 * at them. An unknown or foreign id does nothing, as in the Node API.
 */
class DeactivateDocType
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, string $uuid): void
    {
        DB::transaction(function () use ($actor, $tenant, $uuid): void {
            $type = DocumentType::query()->where('tenant_id', $tenant->id)->where('uuid', $uuid)->first();

            if ($type === null) {
                return;
            }

            $type->forceFill(['is_active' => false])->save();

            $this->recordAuditLog->handle($actor, 'dcs.doc_type.deactivated', $type, ['is_active' => true], ['is_active' => false], $tenant->id);
        });
    }
}
