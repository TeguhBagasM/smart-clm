<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\NumberAllocator;
use App\Support\Dcs\NumberingMask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a controlled document with its first draft version. The control
 * number comes from the numbering rule (and is consumed for good) or, for an
 * uploaded PDF, is typed by the user and leaves the counters alone. The number,
 * the document, the version and their history commit together, so a failure
 * leaves nothing behind and returns the number.
 */
class CreateDocument
{
    public function __construct(
        private NumberAllocator $numberAllocator,
        private FindOrCreateDepartment $findOrCreateDepartment,
        private RecordDocumentEvent $recordDocumentEvent,
    ) {}

    /**
     * @param  array{
     *     docTypeCode: string, department: string, title: string, ownerUserId: string|null, manualNumber: string|null,
     *     initialMajor: int|null, initialMinor: int|null, archiveCategory: string|null, archiveSubFolderId: string|null
     * }  $data
     * @return array{document: Document, version: DocumentVersion}
     */
    public function handle(User $actor, Tenant $tenant, array $data): array
    {
        $number = null;

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, &$number): array {
                $type = $tenant->dcsDocumentTypes()->where('code', $data['docTypeCode'])->where('is_active', true)->first()
                    ?? throw DcsException::badRequest("Unknown document type: {$data['docTypeCode']}");

                $department = $this->findOrCreateDepartment->handle($actor, $tenant, $data['department']);
                $owner = $data['ownerUserId'] === null ? $actor : $this->owner($tenant, $data['ownerUserId']);

                $manual = mb_substr(trim((string) $data['manualNumber']), 0, 80);
                $allocation = null;

                if ($manual !== '') {
                    $number = $manual;
                } else {
                    $rule = NumberingRule::effectiveFor($tenant, $type) ?? throw DcsException::badRequest('Aturan penomoran belum tersedia untuk perusahaan ini.');
                    $tokens = ['DocType' => $type->code, 'Department' => $department->code, ...$this->numberAllocator->clockTokens()];
                    $allocation = $this->numberAllocator->allocate($rule, $tokens);
                    $number = NumberingMask::render($rule->mask, $tokens, $allocation->seq);
                }

                $document = new Document;

                $document->forceFill([
                    'tenant_id' => $tenant->id,
                    'document_number' => $number,
                    'doc_type_id' => $type->id,
                    'department_id' => $department->id,
                    'title' => $data['title'],
                    'owner_user_id' => $owner->id,
                    'archive_category' => $data['archiveCategory'],
                    'archive_sub_folder_id' => $data['archiveSubFolderId'],
                    'created_by_id' => $actor->id,
                ])->save();

                $allocation?->forceFill(['document_id' => $document->id])->save();

                $version = new DocumentVersion;

                $version->forceFill([
                    'tenant_id' => $tenant->id,
                    'document_id' => $document->id,
                    'major' => $data['initialMajor'] ?? 1,
                    'minor' => $data['initialMinor'] ?? 0,
                    'status' => VersionStatus::Draft,
                    'created_by_id' => $actor->id,
                ])->save();

                $this->recordDocumentEvent->handle($document, DocumentEventType::DocumentCreated, $actor, payload: [
                    'number' => $number,
                    'doc_type' => $type->code,
                    'department' => $department->code,
                    'title' => $document->title,
                ]);

                if ($allocation !== null) {
                    $this->recordDocumentEvent->handle($document, DocumentEventType::NumberAllocated, $actor, payload: [
                        'number' => $number,
                        'seq' => $allocation->seq,
                    ]);
                }

                $this->recordDocumentEvent->handle($document, DocumentEventType::VersionCreated, $actor, $version, [
                    'major' => $version->major,
                    'minor' => $version->minor,
                    'source' => 'initial',
                ], to: VersionStatus::Draft);

                return ['document' => $document, 'version' => $version];
            });
        } catch (UniqueConstraintViolationException) {
            throw DcsException::conflict("Nomor dokumen {$number} sudah dipakai oleh dokumen lain.");
        }
    }

    private function owner(Tenant $tenant, string $legacyId): User
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('legacy_id', $legacyId)
            ->first() ?? throw DcsException::badRequest('Pemilik dokumen tidak ditemukan.');
    }
}
