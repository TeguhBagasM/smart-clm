<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Dcs\BodyStyle;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use App\Models\Dcs\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes only what the request supplies. A name or description cannot be
 * cleared, as in the Node API.
 */
class UpdateDocType
{
    public function __construct(private SyncDocTypeTemplate $syncDocTypeTemplate, private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{
     *     name: string|null, description: string|null, layoutStyle: LayoutStyle|null, headerStyle: HeaderStyle|null,
     *     letterhead: bool|null, documentLevel: string|null, processOwner: string|null, headerLayout?: array<string, mixed>|null, bodyStyle?: BodyStyle|null, template: array<string, mixed>
     * }  $data
     */
    public function handle(User $actor, DocumentType $type, array $data): DocumentType
    {
        return DB::transaction(function () use ($actor, $type, $data): DocumentType {
            $before = $this->snapshot($type);

            $attributes = array_filter([
                'name' => $data['name'],
                'description' => $data['description'],
                'layout_style' => $data['layoutStyle'],
                'header_style' => $data['headerStyle'],
                'letterhead' => $data['letterhead'],
                'document_level' => $data['documentLevel'],
                'process_owner' => $data['processOwner'],
                'body_style' => $data['bodyStyle'] ?? null,
                'header_layout' => $data['headerLayout'] ?? null,
            ], fn (mixed $value): bool => $value !== null);

            $type->forceFill($attributes)->save();

            $this->syncDocTypeTemplate->handle($type->tenant, $type, $data['template']);

            $this->recordAuditLog->handle($actor, 'dcs.doc_type.updated', $type, $before, $this->snapshot($type->refresh()), $type->tenant_id);

            return $type;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DocumentType $type): array
    {
        return [
            'name' => $type->name,
            'description' => $type->description,
            'layout_style' => $type->layout_style->value,
            'header_style' => $type->header_style->value,
            'letterhead' => $type->letterhead,
            'document_level' => $type->document_level,
            'process_owner' => $type->process_owner,
            'steps' => $type->steps()->count(),
            'sections' => $type->sections()->count(),
        ];
    }
}
