<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a deactivated document type back. Types are never deleted (documents
 * refer to them), only switched off.
 */
class ReactivateDocType
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, DocumentType $type): void
    {
        DB::transaction(function () use ($actor, $tenant, $type): void {
            $type->forceFill(['is_active' => true])->save();

            $this->recordAuditLog->handle($actor, 'dcs.doc_type.reactivated', $type, ['is_active' => false], ['is_active' => true], $tenant->id);
        });
    }
}
