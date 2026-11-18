<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionAttachment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves any part of a draft's content. Parts missing from `$content` are left as
 * they are; a part that is present replaces the stored one. Only a draft can be
 * changed. When `$expectedRevision` is given and someone saved since, the save is
 * refused (409) so two editors do not silently overwrite each other.
 *
 * Content keys: purpose, scope, languageMode, sections, flowMode, flowDisplayStyle,
 * flowLanes, flowSteps, appendix, clauseIds, signatureAssignments, extraReviewers.
 */
class SaveDraftContent
{
    public function __construct(private BuildVersionSteps $buildVersionSteps) {}

    /**
     * @param  array<string, mixed>  $content
     *
     * @throws DcsException
     * @throws ValidationException
     */
    public function handle(User $actor, Tenant $tenant, DocumentVersion $version, array $content, ?UploadedFile $flowImage = null, ?int $expectedRevision = null): VersionContent
    {
        $replacedImageKey = null;

        $saved = DB::transaction(function () use ($tenant, $version, $content, $flowImage, $expectedRevision, &$replacedImageKey): VersionContent {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::Draft) {
                throw DcsException::conflict('Konten hanya bisa disusun/diubah selagi versi berstatus Draft.');
            }

            $row = VersionContent::query()->where('version_id', $locked->id)->first();

            if ($row !== null && $expectedRevision !== null && $row->draft_revision !== $expectedRevision) {
                throw DcsException::conflict('Draft sudah diubah di tempat lain. Muat ulang lalu coba lagi.');
            }

            $creating = $row === null;
            $row ??= (new VersionContent)->forceFill([
                'version_id' => $locked->id, 'language_mode' => 'single', 'flow_mode' => 'none', 'flow_display_style' => 'diagram',
                'flow_lanes' => [], 'flow_steps' => [],
            ]);

            $this->fillScalars($row, $content);
            $replacedImageKey = $this->fillFlowImage($row, $locked, $flowImage);
            $row->forceFill(['draft_revision' => $creating ? 1 : $row->draft_revision + 1])->save();

            if (array_key_exists('sections', $content)) {
                $this->writeSections($locked, $content['sections']);
            }

            if (array_key_exists('appendix', $content)) {
                $this->writeAttachments($locked, $content['appendix']);
            }

            if (array_key_exists('clauseIds', $content)) {
                $this->writeClauses($tenant, $locked, $content['clauseIds']);
            }

            if (array_key_exists('signatureAssignments', $content) || array_key_exists('extraReviewers', $content) || ! $locked->steps()->exists()) {
                $this->buildVersionSteps->handle($tenant, $locked, $content['signatureAssignments'] ?? [], $content['extraReviewers'] ?? []);
            }

            return $row;
        });

        if ($replacedImageKey !== null) {
            Storage::disk(config('dcs.disk'))->delete($replacedImageKey);
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function fillScalars(VersionContent $row, array $content): void
    {
        foreach (['purpose', 'scope'] as $key) {
            if (array_key_exists($key, $content)) {
                $row->{$key} = $content[$key] === '' ? null : $content[$key];
            }
        }

        $mapping = ['languageMode' => 'language_mode', 'flowMode' => 'flow_mode', 'flowDisplayStyle' => 'flow_display_style', 'flowLanes' => 'flow_lanes', 'flowSteps' => 'flow_steps'];

        foreach ($mapping as $key => $column) {
            if (array_key_exists($key, $content) && $content[$key] !== null) {
                $row->{$column} = $content[$key];
            }
        }

        if ($row->flow_mode !== 'builder') {
            $row->flow_steps = [];
        }
    }

    /**
     * Stores a newly uploaded flow image and returns the key of the one it replaces.
     */
    private function fillFlowImage(VersionContent $row, DocumentVersion $version, ?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $previous = $row->flow_image_key;
        $key = sprintf('tenant-%d/versions/%s/flow-%s.%s', $version->tenant_id, $version->uuid, Str::lower((string) Str::ulid()), $image->guessExtension() ?: 'png');

        Storage::disk(config('dcs.disk'))->put($key, (string) file_get_contents($image->getRealPath()));
        $row->forceFill(['flow_image_key' => $key, 'flow_image_mime' => $image->getMimeType() ?? 'image/png']);

        return $previous;
    }

    /**
     * @param  list<array{heading: string, content: string, table?: array<string, mixed>|null}>  $sections
     */
    private function writeSections(DocumentVersion $version, array $sections): void
    {
        VersionSection::query()->where('version_id', $version->id)->delete();

        foreach ($sections as $position => $section) {
            VersionSection::query()->forceCreate([
                'version_id' => $version->id,
                'position' => $position + 1,
                'heading' => $section['heading'],
                'content' => $section['content'],
                'table_data' => $section['table'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<array{label: string, url: string}>  $attachments
     */
    private function writeAttachments(DocumentVersion $version, array $attachments): void
    {
        VersionAttachment::query()->where('version_id', $version->id)->delete();

        foreach ($attachments as $position => $attachment) {
            VersionAttachment::query()->forceCreate(['version_id' => $version->id, 'position' => $position + 1, 'label' => $attachment['label'], 'url' => $attachment['url']]);
        }
    }

    /**
     * @param  list<string>  $uuids
     *
     * @throws ValidationException
     */
    private function writeClauses(Tenant $tenant, DocumentVersion $version, array $uuids): void
    {
        $uuids = array_values(array_unique($uuids));
        $clauses = $tenant->dcsClauses()->whereIn('uuid', $uuids)->pluck('id', 'uuid');
        $unknown = array_diff($uuids, $clauses->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(['clauseIds' => 'Klausul tidak ditemukan: '.implode(', ', $unknown).'.']);
        }

        DB::table('dcs_version_clauses')->where('version_id', $version->id)->delete();

        foreach ($uuids as $position => $uuid) {
            DB::table('dcs_version_clauses')->insert(['version_id' => $version->id, 'clause_id' => $clauses[$uuid], 'position' => $position + 1]);
        }
    }
}
