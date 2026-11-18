<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Distribution;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\NumberAllocation;
use App\Models\Dcs\NumberingRule;
use App\Models\Dcs\NumberSequence;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\Legacy\ImportReport;
use App\Support\Dcs\Legacy\LegacyRows;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The way back: writes one tenant's Smart DCS data from Laravel into the Node
 * database, so Node can take over again after a rollback. The inverse of
 * ImportLegacyTenant, with the same all-or-nothing rule: one transaction on the
 * Node database per tenant, rolled back on any error or count mismatch.
 *
 * Rows are matched on the public uuid (the Node id); numbering counters on
 * (rule, scope), approvals and receipts on their natural keys. Columns Laravel
 * does not know are left as they are, and unknown keys inside Node's `metadata`
 * JSON are kept. Files go to the `legacy` disk under content-addressed names
 * (`dcs-export-<sha256>.pdf`), so repeating the export stores nothing twice.
 *
 * Things that cannot go back: outside review links (only the token's hash is
 * kept, Node needs the token itself; a warning counts them), and anything that
 * references a Laravel user without a `legacy_id` (Node's users live in its JSON
 * database and are not created here), which is an error.
 */
class ExportLegacyTenant
{
    private ConnectionInterface $target;

    private Filesystem $legacyDisk;

    private Tenant $tenant;

    private string $legacyTenantId;

    private ImportReport $report;

    private bool $dryRun = false;

    private bool $withFiles = true;

    /** @var array<int, string> laravel user id => node user id */
    private array $legacyUserIds = [];

    /** @var list<string> */
    private array $storedFiles = [];

    public function handle(Tenant $tenant, bool $dryRun, bool $withFiles): ImportReport
    {
        $this->tenant = $tenant;
        $this->legacyTenantId = (string) $tenant->legacy_id;
        $this->report = new ImportReport($this->legacyTenantId);
        $this->dryRun = $dryRun;
        $this->withFiles = $withFiles;
        $this->target = DB::connection(config('dcs.legacy.connection'));
        $this->legacyDisk = Storage::disk(config('dcs.legacy.disk'));
        $this->legacyUserIds = User::query()->whereNotNull('legacy_id')->pluck('legacy_id', 'id')->all();
        $this->storedFiles = [];

        try {
            $this->target->transaction(function (): void {
                $this->exportClauses();
                $types = $this->exportDocumentTypes();
                $rules = $this->exportNumbering($types);
                $this->exportDocuments($types, $rules);
                $this->exportVersions();
                $this->exportComments();
                $this->exportDistributions();

                $this->report->reconcile();

                if ($this->report->hasErrors() || $this->dryRun) {
                    throw new RuntimeException('rollback');
                }

                $this->report->committed = true;
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'rollback') {
                $this->report->error($e->getMessage());
            }
        }

        if (! $this->report->committed) {
            foreach ($this->storedFiles as $key) {
                $this->legacyDisk->delete($key);
            }
        }

        return $this->report;
    }

    private function nodeUser(?int $id, string $what, bool $required = true): ?string
    {
        if ($id === null) {
            return null;
        }

        $legacy = $this->legacyUserIds[$id] ?? null;

        if ($legacy === null && $required) {
            $this->report->error("Pengguna Laravel #{$id} ({$what}) belum punya legacy_id, jadi Node tidak bisa merujuknya.");
        }

        return $legacy;
    }

    private function time(?CarbonInterface $value): ?string
    {
        return $value?->copy()->utc()->format('Y-m-d H:i:s.uP');
    }

    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function put(string $table, array $key, array $values): void
    {
        $this->target->table($table)->updateOrInsert($key, $values);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataOf(string $table, string $column, mixed $value): array
    {
        $row = $this->target->table($table)->where($column, $value)->first();

        return $row === null ? [] : LegacyRows::json($row->metadata);
    }

    /**
     * @param  array<int, string>  $items
     */
    private function pgArray(array $items): string
    {
        return '{'.implode(',', array_map(fn (string $item): string => preg_match('/^[A-Za-z0-9_.\-]+$/', $item) ? $item : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $item).'"', $items)).'}';
    }

    private function exportClauses(): void
    {
        $clauses = Clause::query()->where('tenant_id', $this->tenant->id)->with('documentTypes')->orderBy('id')->get();
        $this->report->source('klausul', $clauses->count());

        foreach ($clauses as $clause) {
            $this->put('dcs_clauses', ['id' => $clause->uuid], [
                'tenant_id' => $this->legacyTenantId, 'title' => $clause->title, 'content' => $clause->content, 'category' => $clause->category,
                'applicable_doc_type_codes' => $this->pgArray($clause->documentTypes->pluck('code')->all()), 'is_mandatory' => $clause->is_mandatory,
                'tags' => json_encode($clause->tags), 'is_active' => $clause->is_active, 'created_by' => $this->nodeUser($clause->created_by_id, "pembuat klausul {$clause->title}", false) ?? 'system',
                'created_at' => $this->time($clause->created_at), 'updated_at' => $this->time($clause->updated_at),
            ]);
            $this->report->imported('klausul');
        }
    }

    /**
     * @return array<int, DocumentType>
     */
    private function exportDocumentTypes(): array
    {
        $types = DocumentType::query()->where('tenant_id', $this->tenant->id)->orderBy('id')->get();
        $this->report->source('jenis dokumen', $types->count());
        $out = [];

        foreach ($types as $type) {
            $steps = DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->orderBy('group_position')->orderBy('position')->get()->groupBy('group_position');
            $roleKeys = DB::table('roles')->whereIn('id', DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->whereNotNull('role_id')->pluck('role_id'))->pluck('key', 'id');
            $groups = $steps->map(fn ($group): array => [
                ...($group->first()->group_heading === null ? [] : ['heading' => $group->first()->group_heading]),
                'columns' => $group->map(fn ($step): array => ['label' => $step->label, 'role' => $step->role_id === null ? '' : $roleKeys[$step->role_id], ...($step->role_name === null ? [] : ['roleName' => $step->role_name])])->values()->all(),
            ])->values()->all();

            $metadata = [
                ...$this->metadataOf('dcs_document_types', 'id', $type->uuid),
                'defaultSections' => DB::table('dcs_document_type_sections')->where('doc_type_id', $type->id)->orderBy('position')->get()->map(fn ($section): array => ['heading' => $section->heading, 'content' => $section->content])->all(),
                'signatureGroups' => $groups, 'signatureColumns' => collect($groups)->flatMap(fn (array $group): array => $group['columns'])->values()->all(),
                'layoutStyle' => $type->layout_style->value, 'letterhead' => $type->letterhead, 'defaultLanes' => $type->default_lanes,
                'defaultClauseIds' => DB::table('dcs_document_type_default_clauses')->where('doc_type_id', $type->id)->orderBy('position')->pluck('clause_id')->map(fn ($id): string => (string) Clause::query()->whereKey($id)->value('uuid'))->all(),
                'headerStyle' => $type->header_style->value, 'documentLevel' => $type->document_level, 'processOwner' => $type->process_owner,
            ];

            $this->put('dcs_document_types', ['id' => $type->uuid], [
                'tenant_id' => $this->legacyTenantId, 'code' => $type->code, 'name' => $type->name, 'description' => $type->description, 'is_active' => $type->is_active,
                'metadata' => json_encode($metadata), 'created_at' => $this->time($type->created_at), 'updated_at' => $this->time($type->updated_at),
            ]);

            $out[$type->id] = $type;
            $this->report->imported('jenis dokumen');
        }

        return $out;
    }

    /**
     * @param  array<int, DocumentType>  $types
     * @return array<int, NumberingRule>
     */
    private function exportNumbering(array $types): array
    {
        $rules = NumberingRule::query()->where('tenant_id', $this->tenant->id)->orderBy('id')->get()->keyBy('id');
        $this->report->source('aturan nomor', $rules->count());

        foreach ($rules as $rule) {
            $this->put('dcs_numbering_rules', ['id' => $rule->uuid], [
                'tenant_id' => $this->legacyTenantId, 'name' => $rule->name, 'mask' => $rule->mask, 'scope_tokens' => json_encode($rule->scope_tokens),
                'doc_type_id' => $rule->doc_type_id === null ? null : $types[$rule->doc_type_id]->uuid, 'is_active' => $rule->is_active,
                'created_at' => $this->time($rule->created_at), 'updated_at' => $this->time($rule->updated_at),
            ]);
            $this->report->imported('aturan nomor');
        }

        $sequences = NumberSequence::query()->where('tenant_id', $this->tenant->id)->orderBy('id')->get();
        $this->report->source('counter nomor', $sequences->count());

        foreach ($sequences as $sequence) {
            $rule = $rules[$sequence->rule_id];
            $pool = NumberAllocation::query()->where('sequence_id', $sequence->id)->whereNotNull('released_at')->orderBy('seq')->pluck('seq')->map(fn ($seq): string => (string) $seq)->all();
            $existing = $this->target->table('dcs_number_sequences')->where('tenant_id', $this->legacyTenantId)->where('rule_id', $rule->uuid)->where('scope_hash', $sequence->scope_hash)->first();

            $this->put('dcs_number_sequences', ['id' => $existing->id ?? (string) Str::uuid()], [
                'tenant_id' => $this->legacyTenantId, 'rule_id' => $rule->uuid, 'scope_hash' => $sequence->scope_hash, 'scope_values' => json_encode($sequence->scope_values),
                'current_seq' => $sequence->current_seq, 'released' => $this->pgArray($pool), 'updated_at' => $this->time($sequence->updated_at),
            ]);
            $this->report->imported('counter nomor');
        }

        return $rules->all();
    }

    /**
     * @param  array<int, DocumentType>  $types
     * @param  array<int, NumberingRule>  $rules
     */
    private function exportDocuments(array $types, array $rules): void
    {
        $documents = Document::query()->where('tenant_id', $this->tenant->id)->with('department')->orderBy('id')->get();
        $this->report->source('dokumen', $documents->count());

        foreach ($documents as $document) {
            $allocation = NumberAllocation::query()->where('document_id', $document->id)->first();
            $sequence = $allocation === null ? null : NumberSequence::query()->find($allocation->sequence_id);
            $metadata = $this->metadataOf('dcs_documents', 'id', $document->uuid);

            foreach (['archiveCategory' => $document->archive_category, 'archiveSubFolderId' => $document->archive_sub_folder_id] as $key => $value) {
                $value === null ? $metadata = array_diff_key($metadata, [$key => true]) : $metadata[$key] = $value;
            }

            $this->put('dcs_documents', ['id' => $document->uuid], [
                'tenant_id' => $this->legacyTenantId, 'document_number' => $document->document_number, 'doc_type_id' => $types[$document->doc_type_id]->uuid, 'department_id' => $document->department->code,
                'title' => $document->title, 'owner_user_id' => $this->nodeUser($document->owner_user_id, "pemilik dokumen {$document->document_number}"),
                'metadata' => json_encode((object) $metadata), 'created_by' => $this->nodeUser($document->created_by_id, "pembuat dokumen {$document->document_number}"),
                'number_seq' => $allocation?->seq, 'number_rule_id' => $sequence === null ? null : $rules[$sequence->rule_id]->uuid, 'number_scope_hash' => $sequence?->scope_hash,
                'created_at' => $this->time($document->created_at), 'updated_at' => $this->time($document->updated_at),
            ]);
            $this->report->imported('dokumen');
        }
    }

    private function exportVersions(): void
    {
        $versions = DocumentVersion::query()->where('tenant_id', $this->tenant->id)->with(['document', 'content'])->orderBy('id')->get();
        $this->report->source('versi', $versions->count());
        $this->report->source('tanda tangan', Approval::query()->whereIn('version_id', $versions->pluck('id'))->whereNull('voided_at')->count());

        foreach ($versions as $version) {
            $document = $version->document;
            $label = "{$document->document_number} {$version->major}.{$version->minor}";
            $existing = LegacyRows::json($this->target->table('dcs_document_versions')->where('id', $version->uuid)->value('metadata'));
            $metadata = $existing;

            foreach (['compose', 'scheduledEffectiveAt', 'lastReminderDate', 'externalReview'] as $key) {
                unset($metadata[$key]);
            }

            $metadata['reviewRound'] = $version->review_round;
            $metadata['reviewReminderDays'] = $version->review_reminder_days;

            if ($version->scheduled_effective_at !== null) {
                $metadata['scheduledEffectiveAt'] = ApiDate::format($version->scheduled_effective_at);
            }

            if ($version->last_review_reminder_on !== null) {
                $metadata['lastReminderDate'] = $version->last_review_reminder_on->toDateString();
            }

            if ($version->content !== null) {
                $metadata['compose'] = $this->compose($version);
            }

            $links = $version->externalReviews()->whereNull('disabled_at')->count();

            if ($links > 0) {
                $this->report->warn("Versi {$label}: {$links} link review eksternal tidak bisa diekspor (Laravel hanya menyimpan hash token); link harus dibuat ulang di Node.");
            }

            $main = $version->currentRendition();
            $translated = $version->renditions()->where('kind', RenditionKind::Translated->value)->reorder()->orderByDesc('id')->first();
            $mainFile = $main === null ? null : $this->exportFile($main, $label);
            $translatedFile = $translated === null ? null : $this->exportFile($translated, $label);

            $this->put('dcs_document_versions', ['id' => $version->uuid], [
                'tenant_id' => $this->legacyTenantId, 'document_id' => $document->uuid, 'major_version' => $version->major, 'minor_version' => $version->minor, 'status' => $version->status->value,
                'clean_file_s3_key' => $mainFile['key'] ?? null, 'clean_file_sha256' => $mainFile['sha256'] ?? null, 'file_size_bytes' => $mainFile['size'] ?? null, 'mime_type' => 'application/pdf',
                'translated_file_s3_key' => $translatedFile['key'] ?? null, 'translated_file_sha256' => $translatedFile['sha256'] ?? null, 'translated_lang' => $translatedFile === null ? null : $translated->language,
                'change_summary' => $version->change_summary, 'effective_at' => $this->time($version->effective_at), 'superseded_at' => $this->time($version->superseded_at), 'review_due_at' => $this->time($version->review_due_at),
                'metadata' => json_encode((object) $metadata), 'created_by' => $this->nodeUser($version->created_by_id, "pembuat versi {$label}"),
                'created_at' => $this->time($version->created_at), 'updated_at' => $this->time($version->updated_at),
            ]);
            $this->report->imported('versi');

            $this->exportApprovals($version, $label);
        }

        foreach ($versions->pluck('document')->unique('id') as $document) {
            $this->target->table('dcs_documents')->where('id', $document->uuid)->update(['current_version_id' => $document->current_version_id === null ? null : DocumentVersion::query()->whereKey($document->current_version_id)->value('uuid')]);
        }
    }

    /**
     * The compose metadata Node keeps on a version, in the shape its own compose route saves.
     *
     * @return array<string, mixed>
     */
    private function compose(DocumentVersion $version): array
    {
        $content = $version->content;
        $steps = VersionStep::query()->where('version_id', $version->id)->with('role')->orderBy('group_position')->orderBy('position')->get()->groupBy('group_position');
        $groups = $steps->map(fn ($group): array => [
            ...($group->first()->group_heading === null ? [] : ['heading' => $group->first()->group_heading]),
            'columns' => $group->map(fn (VersionStep $step): array => [
                'label' => $step->label, 'role' => $step->role->key ?? '', ...($step->role_name === null ? [] : ['roleName' => $step->role_name]),
                ...($step->assigned_user_id === null ? [] : ['assignedUserId' => $this->nodeUser($step->assigned_user_id, "penandatangan kolom {$step->label}")]),
            ])->values()->all(),
        ])->values()->all();

        return [
            'purpose' => $content->purpose ?? '', 'scope' => $content->scope ?? '',
            'sections' => $version->sections()->get()->map(fn ($section): array => ['heading' => $section->heading, 'content' => $section->content, ...($section->table_data === null ? [] : ['table' => $section->table_data])])->all(),
            'flowMode' => $content->flow_mode, 'flowSteps' => $content->flow_steps, 'flowLanes' => $content->flow_lanes, 'flowDisplayStyle' => $content->flow_display_style,
            'hasFlowImage' => $content->flow_image_key !== null,
            'signatureColumns' => collect($groups)->flatMap(fn (array $group): array => $group['columns'])->values()->all(), 'signatureGroups' => $groups,
            'signatureSheetPageIndex' => $content->render_layout['signatureSheetPageIndex'] ?? null, 'sigLayout' => $content->render_layout['sigLayout'] ?? null, 'headerLayout' => $content->render_layout['headerLayout'] ?? null,
            'appendix' => $version->attachments()->get()->map(fn ($item): array => ['label' => $item->label, 'url' => $item->url])->all(),
            'clauseIds' => $version->clauses()->get()->pluck('uuid')->all(), 'languageMode' => $content->language_mode,
        ];
    }

    /**
     * Copies a stored PDF to the Node file store under a name made from its hash.
     *
     * @return array{key: string, sha256: string, size: int}|null null when the file is gone or damaged (an error is reported)
     */
    private function exportFile(Rendition $rendition, string $label): ?array
    {
        $key = "dcs-export-{$rendition->sha256}.pdf";
        $bytes = Storage::disk(config('dcs.disk'))->get($rendition->storage_key);

        if ($bytes === null || ! hash_equals($rendition->sha256, hash('sha256', $bytes))) {
            $this->report->error("Versi {$label}: berkas di Laravel hilang atau hash-nya tidak cocok; tidak diekspor.");

            return null;
        }

        $this->report->found('berkas');

        if (! $this->dryRun && $this->withFiles && ! $this->legacyDisk->exists($key)) {
            $this->legacyDisk->put($key, $bytes);
            $this->storedFiles[] = $key;
        }

        return ['key' => $key, 'sha256' => $rendition->sha256, 'size' => $rendition->size_bytes];
    }

    private function exportApprovals(DocumentVersion $version, string $label): void
    {
        $this->target->table('dcs_approvals')->where('tenant_id', $this->legacyTenantId)->where('version_id', $version->uuid)->delete();

        foreach (Approval::query()->where('version_id', $version->id)->whereNull('voided_at')->orderBy('step_order')->get() as $approval) {
            $this->target->table('dcs_approvals')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $this->legacyTenantId, 'version_id' => $version->uuid, 'step_order' => $approval->step_order,
                'approver_user_id' => $this->nodeUser($approval->approver_user_id, "tanda tangan langkah {$approval->step_order} pada {$label}"), 'approver_role' => $approval->approver_role_key,
                'decision' => 'approved', 'decided_at' => $this->time($approval->decided_at), 'signed_content_sha256' => $approval->signed_content_sha256, 'signature_algorithm' => 'SHA256-STAMP',
                'signature_anchor' => json_encode(['columnIndex' => $approval->step_order - 1, 'columnLabel' => $approval->column_label]),
                'created_at' => $this->time($approval->decided_at), 'updated_at' => $this->time($approval->decided_at),
            ]);
            $this->report->imported('tanda tangan');
        }
    }

    private function exportComments(): void
    {
        $comments = ReviewComment::query()->where('tenant_id', $this->tenant->id)->with('version')->orderBy('id')->get();
        $this->report->source('komentar review', $comments->count());

        foreach ($comments as $comment) {
            $this->put('dcs_review_comments', ['id' => $comment->uuid], [
                'tenant_id' => $this->legacyTenantId, 'version_id' => $comment->version->uuid, 'author_user_id' => $comment->author_user_id === null ? 'external' : $this->nodeUser($comment->author_user_id, "penulis komentar {$comment->uuid}"),
                'author_name' => $comment->author_name, 'author_role' => $comment->author_role_key ?? '', 'anchor' => json_encode((object) $comment->anchor), 'body' => $comment->body,
                'status' => $comment->status->value, 'review_round' => $comment->review_round, 'resolved_by' => $this->nodeUser($comment->resolved_by_id, "penyelesai komentar {$comment->uuid}", false),
                'resolved_by_name' => $comment->resolved_by_name, 'resolved_at' => $this->time($comment->resolved_at), 'created_at' => $this->time($comment->created_at), 'updated_at' => $this->time($comment->updated_at),
            ]);
            $this->report->imported('komentar review');
        }
    }

    private function exportDistributions(): void
    {
        $distributions = Distribution::query()->where('tenant_id', $this->tenant->id)->orderBy('id')->get();
        $receipts = ReadReceipt::query()->where('tenant_id', $this->tenant->id)->with(['user', 'version'])->orderBy('id')->get();
        $this->report->source('distribusi', $distributions->count());
        $this->report->source('tanda terima baca', $receipts->count());

        foreach ($distributions as $distribution) {
            $this->put('dcs_distributions', ['id' => $distribution->uuid], [
                'tenant_id' => $this->legacyTenantId, 'version_id' => DocumentVersion::query()->whereKey($distribution->version_id)->value('uuid'), 'target_scope' => 'user', 'target_value' => "{$distribution->recipient_count} user",
                'is_mandatory' => $distribution->is_mandatory, 'distributed_at' => $this->time($distribution->distributed_at), 'distributed_by' => $this->nodeUser($distribution->distributed_by_id, "pengirim distribusi {$distribution->uuid}"),
                'created_at' => $this->time($distribution->distributed_at), 'updated_at' => $this->time($distribution->distributed_at),
            ]);
            $this->report->imported('distribusi');
        }

        foreach ($receipts as $receipt) {
            $distributionUuid = $receipt->distribution_id === null ? null : Distribution::query()->whereKey($receipt->distribution_id)->value('uuid');
            $nodeUser = $this->nodeUser($receipt->user_id, 'penerima tanda terima baca');
            $existing = $this->target->table('dcs_read_receipts')->where('version_id', $receipt->version->uuid)->where('user_id', $nodeUser)->first();

            $this->put('dcs_read_receipts', ['id' => $existing->id ?? (string) Str::uuid()], [
                'tenant_id' => $this->legacyTenantId, 'version_id' => $receipt->version->uuid, 'distribution_id' => $distributionUuid, 'user_id' => $nodeUser, 'status' => $receipt->status->value,
                'read_at' => $this->time($receipt->read_at), 'acknowledged_at' => $this->time($receipt->acknowledged_at), 'ip_address' => $receipt->ip_address, 'user_agent' => $receipt->user_agent,
                'created_at' => $this->time($receipt->created_at), 'updated_at' => $this->time($receipt->updated_at),
            ]);
            $this->report->imported('tanda terima baca');
        }
    }
}
