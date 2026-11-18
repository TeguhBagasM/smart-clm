<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\ReviewCommentStatus;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Department;
use App\Models\Dcs\Distribution;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ExternalReviewApproval;
use App\Models\Dcs\NumberAllocation;
use App\Models\Dcs\NumberingRule;
use App\Models\Dcs\NumberSequence;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\TenantSetting;
use App\Models\Dcs\VersionAttachment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Legacy\ImportReport;
use App\Support\Dcs\Legacy\LegacyRows;
use App\Support\Dcs\RoleKeyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

/**
 * Copies one tenant's Smart DCS data from the Node database into the Laravel
 * schema. The source is only read. All or nothing per tenant: any error (a user
 * or role that does not exist here, a file whose hash does not match, a count
 * that does not add up) rolls the tenant back, and the report says why.
 *
 * Re-running refreshes: everything is matched on the Node id (kept as the public
 * uuid) and overwritten, children are rewritten, files already stored are not
 * stored twice. Because that would overwrite work done in Laravel, a tenant that
 * already has live activity here is refused unless `$force` is set.
 *
 * Imported history is synthesised from the rows (created, signed, released,
 * superseded) and tagged `source = import`; Node kept no event log.
 */
class ImportLegacyTenant
{
    private ConnectionInterface $source;

    private Filesystem $legacyDisk;

    private Tenant $tenant;

    private string $legacyTenantId;

    private ImportReport $report;

    private bool $dryRun = false;

    private bool $withFiles = true;

    /** @var Collection<string, int> */
    private Collection $users;

    /** @var array<string, DocumentType> */
    private array $types = [];

    /** @var array<string, NumberingRule> */
    private array $rules = [];

    /** @var array<string, Clause> */
    private array $clauses = [];

    /** @var array<string, Department> */
    private array $departments = [];

    /** @var array<string, Document> */
    private array $documents = [];

    /** @var array<string, DocumentVersion> */
    private array $versions = [];

    /** @var array<string, int> node sequence id => laravel sequence id */
    private array $sequenceIds = [];

    /** @var array<string, Distribution> */
    private array $distributions = [];

    public function __construct(
        private SyncDocTypeTemplate $syncDocTypeTemplate,
        private SyncClauseDocTypes $syncClauseDocTypes,
        private StoreRendition $storeRendition,
        private RoleKeyResolver $roleKeyResolver,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings  the tenant's Node settings (company name, logo, margins...)
     */
    public function handle(Tenant $tenant, ?array $settings, bool $dryRun, bool $withFiles, bool $force): ImportReport
    {
        $this->reset($tenant, $dryRun, $withFiles);

        if (! $force && DocumentEvent::query()->where('tenant_id', $tenant->id)->where('source', 'live')->exists()) {
            $this->report->error('Laravel sudah menerima aktivitas langsung untuk tenant ini; impor akan menimpanya. Gunakan --force hanya bila memang disengaja.');

            return $this->report;
        }

        try {
            DB::transaction(function () use ($settings): void {
                $this->importSettings($settings);
                $this->importClauses();
                $this->importDocumentTypes();
                $this->importNumbering();
                $this->importDocuments();
                $this->importVersions();
                $this->importAllocations();
                $this->importReviewComments();
                $this->importDistributions();
                $this->importHistory();

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
        } catch (ValidationException $e) {
            $this->report->error('Data tidak cocok dengan skema baru: '.collect($e->errors())->flatten()->implode(' '));
        }

        return $this->report;
    }

    private function reset(Tenant $tenant, bool $dryRun, bool $withFiles): void
    {
        $this->tenant = $tenant;
        $this->legacyTenantId = (string) $tenant->legacy_id;
        $this->report = new ImportReport($this->legacyTenantId);
        $this->dryRun = $dryRun;
        $this->withFiles = $withFiles;
        $this->source = DB::connection(config('dcs.legacy.connection'));
        $this->legacyDisk = Storage::disk(config('dcs.legacy.disk'));
        $this->users = User::query()->where(fn ($query) => $query->where('tenant_id', $tenant->id)->orWhereNull('tenant_id'))->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $this->types = $this->rules = $this->clauses = $this->departments = $this->documents = $this->versions = $this->sequenceIds = $this->distributions = [];
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function rows(string $table, string $orderBy = 'created_at'): Collection
    {
        $query = $this->source->table($table)->where('tenant_id', $this->legacyTenantId);

        return $query->orderBy($orderBy)->orderBy('id')->get();
    }

    private function userId(?string $legacyId): ?int
    {
        return $legacyId === null ? null : ($this->users[$legacyId] ?? null);
    }

    private function requireUser(?string $legacyId, string $what): int
    {
        return $this->userId($legacyId) ?? throw new RuntimeException("Pengguna Node \"{$legacyId}\" ({$what}) belum ada di Laravel (users.legacy_id).");
    }

    // ---------------------------------------------------------------------------------------------
    // Settings, clauses, document types
    // ---------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function importSettings(?array $raw): void
    {
        if ($raw === null) {
            $this->report->warn('Tidak ada pengaturan tenant (nama perusahaan, logo, margin) yang diberikan; kop dokumen memakai nilai kosong sampai --settings diisi.');

            return;
        }

        $margins = is_array($raw['dcsPageMargins'] ?? null) ? $raw['dcsPageMargins'] : [];
        $logoKey = LegacyRows::text($raw['companyLogoKey'] ?? null);
        $storedLogo = null;

        if ($logoKey !== null) {
            $bytes = $this->legacyDisk->get($logoKey);

            if ($bytes === null) {
                $this->report->warn("Logo perusahaan \"{$logoKey}\" tidak ditemukan di disk legacy; kop dicetak tanpa logo.");
            } elseif (! $this->dryRun && $this->withFiles) {
                $storedLogo = sprintf('tenant-%d/branding/logo-%s', $this->tenant->id, Str::lower((string) Str::ulid()));
                Storage::disk(config('dcs.disk'))->put($storedLogo, $bytes);
            } else {
                $storedLogo = TenantSetting::query()->where('tenant_id', $this->tenant->id)->value('company_logo_key');
            }
        }

        $setting = TenantSetting::query()->where('tenant_id', $this->tenant->id)->first() ?? new TenantSetting;
        $setting->forceFill([
            'tenant_id' => $this->tenant->id,
            'company_name' => LegacyRows::text($raw['companyName'] ?? null),
            'company_address' => LegacyRows::text($raw['companyAddress'] ?? null),
            'company_logo_key' => $storedLogo,
            'company_logo_mime' => $storedLogo === null ? null : LegacyRows::text($raw['companyLogoMimeType'] ?? null),
            'confidentiality_notice' => LegacyRows::text($raw['confidentialityNotice'] ?? null),
            ...collect(['top', 'right', 'bottom', 'left'])->mapWithKeys(fn (string $side): array => ["page_margin_{$side}_mm" => is_numeric($margins[$side] ?? null) && $margins[$side] > 0 ? $margins[$side] : null])->all(),
        ])->save();
    }

    private function importClauses(): void
    {
        $rows = $this->rows('dcs_clauses');
        $this->report->source('klausul', $rows->count());

        foreach ($rows as $row) {
            $clause = Clause::query()->where('tenant_id', $this->tenant->id)->where('uuid', $row->id)->first() ?? new Clause;
            $clause->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'title' => $row->title, 'content' => $row->content,
                'category' => $row->category ?: 'General', 'is_mandatory' => (bool) $row->is_mandatory, 'tags' => LegacyRows::json($row->tags),
                'is_active' => (bool) $row->is_active, 'created_by_id' => $this->userId($row->created_by ?? null),
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->clauses[$row->id] = $clause;
            $this->report->imported('klausul');
        }
    }

    private function roleKey(mixed $key): ?string
    {
        $key = trim((string) $key);

        return match ($key) {
            '' => null,
            'manager' => 'manajemen',
            default => $key,
        };
    }

    private function importDocumentTypes(): void
    {
        $rows = $this->rows('dcs_document_types');
        $this->report->source('jenis dokumen', $rows->count());

        foreach ($rows as $row) {
            $meta = LegacyRows::json($row->metadata);
            $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', $row->code)->first() ?? new DocumentType;
            $layout = in_array($meta['layoutStyle'] ?? null, ['standard', 'memo', 'kebijakan'], true) ? $meta['layoutStyle'] : 'standard';

            $type->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'code' => $row->code, 'name' => $row->name, 'description' => $row->description,
                'layout_style' => $layout,
                'header_style' => in_array($meta['headerStyle'] ?? null, ['iso', 'simple'], true) ? $meta['headerStyle'] : ($layout === 'memo' ? 'simple' : 'iso'),
                'letterhead' => (bool) ($meta['letterhead'] ?? false),
                'document_level' => is_string($meta['documentLevel'] ?? null) && trim($meta['documentLevel']) !== '' ? $meta['documentLevel'] : 'Internal',
                'process_owner' => is_string($meta['processOwner'] ?? null) ? $meta['processOwner'] : '',
                'default_lanes' => [], 'is_active' => (bool) $row->is_active,
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $groups = is_array($meta['signatureGroups'] ?? null) && $meta['signatureGroups'] !== []
                ? $meta['signatureGroups']
                : (is_array($meta['signatureColumns'] ?? null) && $meta['signatureColumns'] !== [] ? [['columns' => $meta['signatureColumns']]] : []);
            $groups = array_values(array_map(fn (array $group): array => [
                'heading' => LegacyRows::text($group['heading'] ?? null),
                'columns' => array_values(array_map(fn (array $column): array => [
                    'label' => (string) ($column['label'] ?? ''), 'role' => $this->roleKey($column['role'] ?? ''), 'roleName' => LegacyRows::text($column['roleName'] ?? null),
                ], $group['columns'] ?? [])),
            ], $groups));
            $flatLabels = collect($groups)->flatMap(fn (array $group): array => array_column($group['columns'], 'label'))->all();

            $this->syncDocTypeTemplate->handle($this->tenant, $type, [
                'sections' => array_values(array_map(fn (array $section): array => ['heading' => (string) ($section['heading'] ?? ''), 'content' => LegacyRows::text($section['content'] ?? null)], is_array($meta['defaultSections'] ?? null) ? $meta['defaultSections'] : [])),
                'groups' => $groups,
                'lanes' => is_array($meta['defaultLanes'] ?? null) && $meta['defaultLanes'] !== [] ? array_values(array_map('strval', $meta['defaultLanes'])) : array_values($flatLabels),
                'defaultClauseIds' => array_values(array_map('strval', is_array($meta['defaultClauseIds'] ?? null) ? $meta['defaultClauseIds'] : [])),
            ]);

            $this->types[$row->id] = $type;
            $this->report->imported('jenis dokumen');
        }

        foreach ($this->source->table('dcs_clauses')->where('tenant_id', $this->legacyTenantId)->get() as $row) {
            $this->syncClauseDocTypes->handle($this->tenant, $this->clauses[$row->id], LegacyRows::list($row->applicable_doc_type_codes));
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Numbering
    // ---------------------------------------------------------------------------------------------

    private function importNumbering(): void
    {
        $rules = $this->rows('dcs_numbering_rules');
        $this->report->source('aturan nomor', $rules->count());

        foreach ($rules as $row) {
            $type = $row->doc_type_id === null ? null : ($this->types[$row->doc_type_id] ?? throw new RuntimeException("Aturan nomor {$row->id} menunjuk jenis dokumen {$row->doc_type_id} yang tidak ada."));
            $rule = NumberingRule::query()->where('tenant_id', $this->tenant->id)->where('doc_type_id', $type?->id)->first() ?? new NumberingRule;
            $rule->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'doc_type_id' => $type?->id, 'name' => $row->name, 'mask' => $row->mask,
                'scope_tokens' => array_values(LegacyRows::json($row->scope_tokens)), 'is_active' => (bool) $row->is_active,
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->rules[$row->id] = $rule;
            $this->report->imported('aturan nomor');
        }

        $sequences = $this->rows('dcs_number_sequences', 'updated_at');
        $this->report->source('counter nomor', $sequences->count());

        foreach ($sequences as $row) {
            $rule = $this->rules[$row->rule_id] ?? throw new RuntimeException("Counter {$row->id} menunjuk aturan nomor {$row->rule_id} yang tidak ada.");
            $sequence = NumberSequence::query()->where('rule_id', $rule->id)->where('scope_hash', $row->scope_hash)->first() ?? new NumberSequence;
            $sequence->forceFill([
                'tenant_id' => $this->tenant->id, 'rule_id' => $rule->id, 'scope_hash' => $row->scope_hash,
                'scope_values' => LegacyRows::json($row->scope_values), 'current_seq' => (int) $row->current_seq,
                'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->sequenceIds[$row->id] = $sequence->id;
            $this->report->imported('counter nomor');
        }
    }

    /**
     * Rebuilds which numbers are held by documents and which wait in the pool of
     * released numbers, after the documents exist.
     */
    private function importAllocations(): void
    {
        NumberAllocation::query()->whereIn('sequence_id', array_values($this->sequenceIds))->delete();

        $seen = [];
        $now = now();

        foreach ($this->rows('dcs_number_sequences', 'updated_at') as $row) {
            foreach (LegacyRows::list($row->released ?? null) as $seq) {
                $key = $this->sequenceIds[$row->id].'#'.$seq;
                $seen[$key] = true;
                NumberAllocation::query()->forceCreate(['sequence_id' => $this->sequenceIds[$row->id], 'seq' => (int) $seq, 'document_id' => null, 'released_at' => $now]);
            }
        }

        $held = $this->source->table('dcs_documents')->where('tenant_id', $this->legacyTenantId)->whereNotNull('number_seq')->whereNotNull('number_rule_id')->get();

        foreach ($held as $row) {
            $rule = $this->rules[$row->number_rule_id] ?? null;
            $sequence = $rule === null ? null : NumberSequence::query()->where('rule_id', $rule->id)->where('scope_hash', $row->number_scope_hash)->first();

            if ($sequence === null) {
                $this->report->warn("Dokumen {$row->document_number}: counter nomornya tidak ada; nomor dianggap manual.");

                continue;
            }

            $key = $sequence->id.'#'.$row->number_seq;

            if (isset($seen[$key])) {
                $this->report->warn("Nomor urut {$row->number_seq} ({$sequence->scope_hash}) dipegang dokumen {$row->document_number} sekaligus ada di kolam nomor lepas; kolam dikalahkan oleh dokumen.");
                NumberAllocation::query()->where('sequence_id', $sequence->id)->where('seq', (int) $row->number_seq)->delete();
            }

            $seen[$key] = true;
            NumberAllocation::query()->forceCreate(['sequence_id' => $sequence->id, 'seq' => (int) $row->number_seq, 'document_id' => $this->documents[$row->id]->id, 'released_at' => null]);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Documents and versions
    // ---------------------------------------------------------------------------------------------

    private function department(string $code): Department
    {
        $key = Str::lower(trim($code));

        return $this->departments[$key] ??= Department::query()->where('tenant_id', $this->tenant->id)->whereRaw('lower(code) = ?', [$key])->first()
            ?? tap(new Department, fn (Department $department) => $department->forceFill(['tenant_id' => $this->tenant->id, 'code' => trim($code), 'name' => trim($code), 'is_active' => true])->save());
    }

    private function importDocuments(): void
    {
        $rows = $this->rows('dcs_documents');
        $this->report->source('dokumen', $rows->count());

        foreach ($rows as $row) {
            $meta = LegacyRows::json($row->metadata);
            $type = $this->types[$row->doc_type_id] ?? throw new RuntimeException("Dokumen {$row->document_number} menunjuk jenis dokumen {$row->doc_type_id} yang tidak ada.");
            $ownerId = $this->requireUser($row->owner_user_id, "pemilik dokumen {$row->document_number}");
            $creator = $this->userId($row->created_by);

            if ($creator === null) {
                $this->report->warn("Dokumen {$row->document_number}: pembuat \"{$row->created_by}\" tidak ada; pemilik dipakai sebagai pembuat.");
            }

            $document = Document::query()->where('tenant_id', $this->tenant->id)->where('uuid', $row->id)->first() ?? new Document;
            $document->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'document_number' => $row->document_number, 'doc_type_id' => $type->id,
                'department_id' => $this->department($row->department_id)->id, 'title' => $row->title, 'owner_user_id' => $ownerId,
                'archive_category' => LegacyRows::text($meta['archiveCategory'] ?? null), 'archive_sub_folder_id' => LegacyRows::text($meta['archiveSubFolderId'] ?? null),
                'created_by_id' => $creator ?? $ownerId,
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->documents[$row->id] = $document;
            $this->report->imported('dokumen');
        }
    }

    private function importVersions(): void
    {
        $rows = $this->rows('dcs_document_versions');
        $this->report->source('versi', $rows->count());
        $this->report->source('berkas', $rows->filter(fn ($row) => $row->clean_file_s3_key !== null)->count() + $rows->filter(fn ($row) => $row->translated_file_s3_key ?? null)->count());
        $this->report->source('tanda tangan', $this->rows('dcs_approvals')->where('decision', 'approved')->count());

        $approvals = $this->rows('dcs_approvals', 'step_order')->where('decision', 'approved')->groupBy('version_id');

        foreach ($rows as $row) {
            $document = $this->documents[$row->document_id] ?? throw new RuntimeException("Versi {$row->id} menunjuk dokumen {$row->document_id} yang tidak ada.");
            $meta = LegacyRows::json($row->metadata);
            $creator = $this->userId($row->created_by) ?? $document->owner_user_id;

            $version = DocumentVersion::query()->where('tenant_id', $this->tenant->id)->where('uuid', $row->id)->first() ?? new DocumentVersion;
            $version->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'document_id' => $document->id, 'major' => (int) $row->major_version, 'minor' => (int) $row->minor_version,
                'status' => VersionStatus::from($row->status), 'change_summary' => $row->change_summary,
                'effective_at' => LegacyRows::time($row->effective_at), 'superseded_at' => LegacyRows::time($row->superseded_at),
                'review_due_at' => LegacyRows::time($row->review_due_at),
                'review_reminder_days' => (int) ($meta['reviewReminderDays'] ?? 0) > 0 ? (int) $meta['reviewReminderDays'] : 30,
                'last_review_reminder_on' => isset($meta['lastReminderDate']) ? CarbonImmutable::parse($meta['lastReminderDate'])->toDateString() : null,
                'scheduled_effective_at' => LegacyRows::time($meta['scheduledEffectiveAt'] ?? null),
                'review_round' => (int) ($meta['reviewRound'] ?? 0) > 0 ? (int) $meta['reviewRound'] : 1,
                'created_by_id' => $creator,
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->versions[$row->id] = $version;
            $this->report->imported('versi');

            $this->importContent($version, $document, is_array($meta['compose'] ?? null) ? $meta['compose'] : null);
            $versionApprovals = $approvals->get($row->id, collect());
            $mainRendition = $this->importFile($version, $document, $row->clean_file_s3_key, $row->clean_file_sha256, $versionApprovals->isNotEmpty() ? RenditionKind::Signed : (is_array($meta['compose'] ?? null) ? RenditionKind::Composed : RenditionKind::Uploaded), null);

            if ($row->translated_file_s3_key ?? null) {
                $this->importFile($version, $document, $row->translated_file_s3_key, $row->translated_file_sha256, RenditionKind::Translated, $row->translated_lang ?: 'en');
            }

            $this->importApprovals($version, $versionApprovals, $mainRendition);
            $this->importExternalReview($version, $document, is_array($meta['externalReview'] ?? null) ? $meta['externalReview'] : null);
        }

        foreach ($this->rows('dcs_documents') as $row) {
            $current = $row->current_version_id === null ? null : ($this->versions[$row->current_version_id] ?? null);
            $this->documents[$row->id]->forceFill(['current_version_id' => $current?->id])->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $compose
     */
    private function importContent(DocumentVersion $version, Document $document, ?array $compose): void
    {
        if ($compose === null) {
            return;
        }

        $content = VersionContent::query()->where('version_id', $version->id)->first() ?? new VersionContent;
        $flowMode = in_array($compose['flowMode'] ?? null, ['none', 'builder', 'image'], true) ? $compose['flowMode'] : 'none';

        if ($flowMode === 'image') {
            $this->report->warn("Versi {$document->document_number} {$version->major}.{$version->minor}: gambar diagram alir tidak pernah disimpan Node, jadi hanya mode-nya yang terbawa.");
        }

        $content->forceFill([
            'version_id' => $version->id, 'purpose' => LegacyRows::text($compose['purpose'] ?? null), 'scope' => LegacyRows::text($compose['scope'] ?? null),
            'language_mode' => ($compose['languageMode'] ?? 'single') === 'multi' ? 'multi' : 'single', 'flow_mode' => $flowMode,
            'flow_display_style' => ($compose['flowDisplayStyle'] ?? 'diagram') === 'table' ? 'table' : 'diagram',
            'flow_lanes' => array_values(is_array($compose['flowLanes'] ?? null) ? $compose['flowLanes'] : []),
            'flow_steps' => $flowMode === 'builder' && is_array($compose['flowSteps'] ?? null) ? array_values($compose['flowSteps']) : [],
            'render_layout' => ['signatureSheetPageIndex' => $compose['signatureSheetPageIndex'] ?? null, 'sigLayout' => $compose['sigLayout'] ?? null, 'headerLayout' => $compose['headerLayout'] ?? null],
            'draft_revision' => max(1, $content->draft_revision ?? 1),
        ])->save();

        VersionSection::query()->where('version_id', $version->id)->delete();

        foreach (array_values(is_array($compose['sections'] ?? null) ? $compose['sections'] : []) as $position => $section) {
            VersionSection::query()->forceCreate([
                'version_id' => $version->id, 'position' => $position + 1, 'heading' => (string) ($section['heading'] ?? ''), 'content' => (string) ($section['content'] ?? ''),
                'table_data' => isset($section['table']) && is_array($section['table']) ? $section['table'] : null,
            ]);
        }

        VersionAttachment::query()->where('version_id', $version->id)->delete();

        foreach (array_values(is_array($compose['appendix'] ?? null) ? $compose['appendix'] : []) as $position => $item) {
            VersionAttachment::query()->forceCreate(['version_id' => $version->id, 'position' => $position + 1, 'label' => (string) ($item['label'] ?? ''), 'url' => (string) ($item['url'] ?? '')]);
        }

        DB::table('dcs_version_clauses')->where('version_id', $version->id)->delete();

        foreach (array_values(array_unique(array_map('strval', is_array($compose['clauseIds'] ?? null) ? $compose['clauseIds'] : []))) as $position => $clauseUuid) {
            $clause = $this->clauses[$clauseUuid] ?? null;

            if ($clause === null) {
                $this->report->warn("Versi {$document->document_number} {$version->major}.{$version->minor}: klausul {$clauseUuid} sudah tidak ada dan dilewati.");

                continue;
            }

            DB::table('dcs_version_clauses')->insert(['version_id' => $version->id, 'clause_id' => $clause->id, 'position' => $position + 1]);
        }

        $this->importSteps($version, $document, is_array($compose['signatureGroups'] ?? null) ? $compose['signatureGroups'] : []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function importSteps(DocumentVersion $version, Document $document, array $groups): void
    {
        VersionStep::query()->where('version_id', $version->id)->delete();

        $keys = collect($groups)->flatMap(fn (array $group): array => array_map(fn (array $column): ?string => $this->roleKey($column['role'] ?? ''), $group['columns'] ?? []))->filter()->unique()->values()->all();
        $keys = array_values($keys);
        $roles = $keys === [] ? [] : $this->roleKeyResolver->resolve($this->tenant, $keys, 'signatureGroups');

        foreach (array_values($groups) as $groupPosition => $group) {
            foreach (array_values($group['columns'] ?? []) as $position => $column) {
                $key = $this->roleKey($column['role'] ?? '');
                $assigned = LegacyRows::text($column['assignedUserId'] ?? null);

                if ($assigned !== null && $this->userId($assigned) === null) {
                    $this->report->warn("Versi {$document->document_number} {$version->major}.{$version->minor}: penandatangan \"{$assigned}\" pada kolom \"{$column['label']}\" tidak ada; kolom dibuka untuk pemegang peran.");
                }

                VersionStep::query()->forceCreate([
                    'version_id' => $version->id, 'group_position' => $groupPosition + 1, 'group_heading' => LegacyRows::text($group['heading'] ?? null), 'position' => $position + 1,
                    'label' => (string) ($column['label'] ?? ''), 'role_id' => $key === null ? null : $roles[$key], 'role_name' => LegacyRows::text($column['roleName'] ?? null),
                    'assigned_user_id' => $assigned === null ? null : $this->userId($assigned), 'status' => 'pending',
                ]);
            }
        }
    }

    /**
     * Copies a Node file onto the private disk, after checking its SHA-256
     * against the one Node recorded. A file already stored for the version with
     * the same hash is not stored again.
     */
    private function importFile(DocumentVersion $version, Document $document, ?string $key, ?string $sha256, RenditionKind $kind, ?string $language): ?Rendition
    {
        if ($key === null) {
            return null;
        }

        $label = "{$document->document_number} {$version->major}.{$version->minor}";
        $bytes = $this->legacyDisk->get($key);

        if ($bytes === null) {
            $this->report->warn("Versi {$label}: berkas \"{$key}\" tidak ada di disk legacy; versi diimpor tanpa berkas.");
            $this->report->skipped('berkas');

            return null;
        }

        if ($sha256 !== null && ! hash_equals(Str::lower(trim($sha256)), hash('sha256', $bytes))) {
            $this->report->error("Versi {$label}: SHA-256 berkas \"{$key}\" tidak sama dengan yang dicatat Node (berkas berubah atau rusak).");

            return null;
        }

        $existing = Rendition::query()->where('version_id', $version->id)->where('sha256', hash('sha256', $bytes))->first();

        if ($existing !== null || $this->dryRun || ! $this->withFiles) {
            $this->report->imported('berkas');

            return $existing;
        }

        $this->report->imported('berkas');

        return $this->storeRendition->handle(User::query()->findOrFail($version->created_by_id), $version, $kind, $bytes, language: $language);
    }

    /**
     * @param  Collection<int, stdClass>  $approvals
     */
    private function importApprovals(DocumentVersion $version, Collection $approvals, ?Rendition $rendition): void
    {
        Approval::query()->where('version_id', $version->id)->whereNull('voided_at')->delete();
        $columns = VersionStep::query()->where('version_id', $version->id)->orderBy('group_position')->orderBy('position')->pluck('label')->values();

        foreach ($approvals as $row) {
            $approver = $this->requireUser($row->approver_user_id, "tanda tangan langkah {$row->step_order}");
            $anchor = LegacyRows::json($row->signature_anchor);

            if ($rendition === null) {
                $this->report->warn("Tanda tangan langkah {$row->step_order} pada versi {$version->uuid} dilewati karena berkas bertanda tangannya tidak ada.");
                $this->report->skipped('tanda tangan');

                continue;
            }

            Approval::query()->forceCreate([
                'version_id' => $version->id, 'step_order' => (int) $row->step_order,
                'column_label' => Str::limit((string) ($anchor['columnLabel'] ?? $columns->get(((int) $row->step_order) - 1) ?? 'Disetujui oleh'), 100, ''),
                'approver_user_id' => $approver, 'approver_role_key' => $this->roleKey($row->approver_role) ?? '', 'signed_content_sha256' => $row->signed_content_sha256 ?: $rendition->sha256,
                'rendition_id' => $rendition->id, 'review_round' => $version->review_round, 'decided_at' => LegacyRows::time($row->decided_at ?? $row->created_at),
            ]);
            $this->report->imported('tanda tangan');
        }
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private function importExternalReview(DocumentVersion $version, Document $document, ?array $state): void
    {
        if ($state === null || ! is_string($state['token'] ?? null) || $state['token'] === '') {
            return;
        }

        $hash = ExternalReview::hashToken($state['token']);
        $review = ExternalReview::query()->where('token_hash', $hash)->first();

        if ($review === null) {
            ExternalReview::query()->where('version_id', $version->id)->whereNull('disabled_at')->update(['disabled_at' => now()]);
            $review = new ExternalReview;
        }

        $review->forceFill([
            'tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'token_hash' => $hash, 'expires_at' => LegacyRows::time($state['expiresAt'] ?? null),
            'locked' => (bool) ($state['locked'] ?? false), 'created_by_id' => $document->owner_user_id, 'disabled_at' => null,
        ])->save();

        ExternalReviewApproval::query()->where('external_review_id', $review->id)->delete();

        foreach (is_array($state['approvals'] ?? null) ? $state['approvals'] : [] as $approval) {
            ExternalReviewApproval::query()->forceCreate([
                'external_review_id' => $review->id, 'name' => Str::limit((string) ($approval['name'] ?? ''), 80, ''), 'note' => LegacyRows::text($approval['note'] ?? null),
                'approved_at' => LegacyRows::time($approval['approvedAt'] ?? null) ?? now(),
            ]);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Comments, distribution, history
    // ---------------------------------------------------------------------------------------------

    private function importReviewComments(): void
    {
        $rows = $this->rows('dcs_review_comments');
        $this->report->source('komentar review', $rows->count());

        foreach ($rows as $row) {
            $version = $this->versions[$row->version_id] ?? null;

            if ($version === null) {
                $this->report->warn("Komentar {$row->id} menunjuk versi {$row->version_id} yang tidak ada dan dilewati.");
                $this->report->skipped('komentar review');

                continue;
            }

            $external = $row->author_user_id === 'external';
            $author = $external ? null : $this->userId($row->author_user_id);

            if (! $external && $author === null) {
                $this->report->warn("Komentar {$row->id}: penulis \"{$row->author_user_id}\" tidak ada; komentar disimpan tanpa tautan ke pengguna.");
            }

            $comment = ReviewComment::query()->where('uuid', $row->id)->first() ?? new ReviewComment;
            $comment->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'author_user_id' => $author, 'author_name' => Str::limit($row->author_name, 150, ''),
                'author_role_key' => LegacyRows::text($row->author_role), 'anchor' => LegacyRows::json($row->anchor) ?: ['field' => 'general'], 'body' => $row->body,
                'status' => ReviewCommentStatus::from($row->status), 'review_round' => (int) $row->review_round,
                'resolved_by_id' => $this->userId($row->resolved_by), 'resolved_by_name' => LegacyRows::text($row->resolved_by_name), 'resolved_at' => LegacyRows::time($row->resolved_at),
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->report->imported('komentar review');
        }
    }

    private function importDistributions(): void
    {
        $rows = $this->rows('dcs_distributions', 'distributed_at');
        $receipts = $this->rows('dcs_read_receipts');
        $this->report->source('distribusi', $rows->count());
        $this->report->source('tanda terima baca', $receipts->count());

        foreach ($rows as $row) {
            $version = $this->versions[$row->version_id] ?? null;

            if ($version === null) {
                $this->report->warn("Distribusi {$row->id} menunjuk versi yang tidak ada dan dilewati.");
                $this->report->skipped('distribusi');

                continue;
            }

            $distribution = Distribution::query()->where('uuid', $row->id)->first() ?? new Distribution;
            $count = (int) $row->target_value > 0 ? (int) $row->target_value : $receipts->where('distribution_id', $row->id)->count();
            $distribution->forceFill([
                'uuid' => $row->id, 'tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'recipient_count' => $count, 'is_mandatory' => (bool) $row->is_mandatory,
                'distributed_by_id' => $this->userId($row->distributed_by) ?? $version->created_by_id, 'distributed_at' => LegacyRows::time($row->distributed_at),
            ])->save();

            $this->distributions[$row->id] = $distribution;
            $this->report->imported('distribusi');
        }

        foreach ($receipts as $row) {
            $version = $this->versions[$row->version_id] ?? null;
            $user = $this->userId($row->user_id);

            if ($version === null || $user === null) {
                $this->report->warn("Tanda terima baca {$row->id}: ".($version === null ? 'versinya' : "pengguna \"{$row->user_id}\"").' tidak ada; dilewati.');
                $this->report->skipped('tanda terima baca');

                continue;
            }

            $receipt = ReadReceipt::query()->where('version_id', $version->id)->where('user_id', $user)->first() ?? new ReadReceipt;
            $receipt->forceFill([
                'tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'user_id' => $user, 'distribution_id' => isset($this->distributions[$row->distribution_id ?? '']) ? $this->distributions[$row->distribution_id]->id : null,
                'status' => ReceiptStatus::from($row->status), 'read_at' => LegacyRows::time($row->read_at), 'acknowledged_at' => LegacyRows::time($row->acknowledged_at),
                'ip_address' => LegacyRows::text($row->ip_address), 'user_agent' => LegacyRows::text($row->user_agent),
                'created_at' => LegacyRows::time($row->created_at), 'updated_at' => LegacyRows::time($row->updated_at),
            ])->save();

            $this->report->imported('tanda terima baca');
        }
    }

    /**
     * Node kept no event log. The history is rebuilt from the rows' own dates
     * (created, signed, released, superseded) and tagged as imported, once per
     * document: a second run leaves what is there.
     */
    private function importHistory(): void
    {
        foreach ($this->documents as $document) {
            if (DocumentEvent::query()->where('document_id', $document->id)->where('source', 'import')->exists()) {
                continue;
            }

            $this->event($document, null, DocumentEventType::DocumentCreated, $document->created_at, $document->created_by_id);

            foreach ($document->versions()->reorder()->orderBy('id')->get() as $version) {
                $this->event($document, $version, DocumentEventType::VersionCreated, $version->created_at, $version->created_by_id, to: VersionStatus::Draft);

                foreach ($version->approvals()->get() as $approval) {
                    $this->event($document, $version, DocumentEventType::Signed, $approval->decided_at, $approval->approver_user_id, ['column' => $approval->column_label, 'step_order' => $approval->step_order]);
                }

                if ($version->effective_at !== null) {
                    $this->event($document, $version, DocumentEventType::Released, $version->effective_at, null, from: VersionStatus::Approved, to: VersionStatus::Effective);
                }

                if ($version->superseded_at !== null) {
                    $this->event($document, $version, DocumentEventType::Superseded, $version->superseded_at, null, from: VersionStatus::Effective, to: VersionStatus::Superseded);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(Document $document, ?DocumentVersion $version, DocumentEventType $type, mixed $at, ?int $actorId, array $payload = [], ?VersionStatus $from = null, ?VersionStatus $to = null): void
    {
        $actor = $actorId === null ? null : User::query()->find($actorId);
        $event = new DocumentEvent;

        $event->forceFill([
            'occurred_at' => $at ?? now(), 'tenant_id' => $this->tenant->id, 'document_id' => $document->id, 'version_id' => $version?->id, 'event_type' => $type,
            'actor_type' => $actor === null ? ActorType::System : ActorType::User, 'actor_id' => $actor?->id, 'actor_name' => $actor?->name,
            'review_round' => $version?->review_round, 'from_status' => $from, 'to_status' => $to, 'payload' => $payload === [] ? null : $payload, 'source' => 'import',
        ])->save();
    }
}
