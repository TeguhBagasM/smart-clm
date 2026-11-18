<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Dcs\BodyStyle;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateDocType
{
    public function __construct(private SyncDocTypeTemplate $syncDocTypeTemplate, private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{
     *     code: string, name: string, description: string|null, layoutStyle: LayoutStyle|null, headerStyle: HeaderStyle|null,
     *     letterhead: bool|null, documentLevel: string|null, processOwner: string|null, headerLayout?: array<string, mixed>|null, bodyStyle?: BodyStyle|null, template: array<string, mixed>
     * }  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data): DocumentType
    {
        $conflict = DcsException::conflict('Kode jenis dokumen sudah dipakai.');

        if (DocumentType::query()->where('tenant_id', $tenant->id)->where('code', $data['code'])->exists()) {
            throw $conflict;
        }

        try {
            return DB::transaction(function () use ($actor, $tenant, $data): DocumentType {
                $layout = $data['layoutStyle'] ?? LayoutStyle::Standard;
                $type = new DocumentType;

                $type->forceFill([
                    'tenant_id' => $tenant->id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'layout_style' => $layout,
                    'header_style' => $data['headerStyle'] ?? $layout->defaultHeaderStyle(),
                    'letterhead' => $data['letterhead'] ?? false,
                    'document_level' => $data['documentLevel'] ?? 'Internal',
                    'process_owner' => $data['processOwner'] ?? '',
                    'body_style' => $data['bodyStyle'] ?? BodyStyle::Standard,
                    'header_layout' => $data['headerLayout'] ?? null,
                    'default_lanes' => [],
                    'is_active' => true,
                ])->save();

                $template = $data['template'];
                $template['groups'] ??= [];
                $template['sections'] ??= [];

                $this->syncDocTypeTemplate->handle($tenant, $type, $template);

                $this->recordAuditLog->handle($actor, 'dcs.doc_type.created', $type, null, ['code' => $type->code, 'name' => $type->name], $tenant->id);

                return $type;
            });
        } catch (UniqueConstraintViolationException) {
            throw $conflict;
        }
    }
}
