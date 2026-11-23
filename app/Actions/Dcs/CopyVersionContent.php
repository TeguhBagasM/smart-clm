<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionAttachment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Dcs\VersionStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Starts a new draft from the text of an earlier version, so a revision or a new
 * issue is edited instead of written again. It copies what the author wrote
 * (purpose, scope, sections, flow diagram, appendix links, clauses, who signs
 * each column) and nothing that belongs to the earlier version's life: no PDF,
 * no signatures, comments or history. The signature columns come back pending.
 * Call it inside the caller's transaction, after the new version exists.
 */
class CopyVersionContent
{
    /**
     * Returns true when the source had content to copy.
     */
    public function handle(DocumentVersion $source, DocumentVersion $target): bool
    {
        $content = VersionContent::query()->where('version_id', $source->id)->first();

        if ($content === null) {
            return false;
        }

        (new VersionContent)->forceFill([
            'version_id' => $target->id,
            'purpose' => $content->purpose,
            'scope' => $content->scope,
            'language_mode' => $content->language_mode,
            'flow_mode' => $content->flow_mode,
            'flow_display_style' => $content->flow_display_style,
            'flow_lanes' => $content->flow_lanes,
            'flow_steps' => $content->flow_steps,
            'draft_revision' => 1,
        ] + $this->flowImage($content, $target))->save();

        $this->copySections($source, $target);
        $this->copyAttachments($source, $target);
        $this->copyClauses($source, $target);
        $this->copySteps($source, $target);

        return true;
    }

    /**
     * The picture is copied to its own file: replacing it in the new draft deletes the old key, and
     * that must never remove the earlier version's picture.
     *
     * @return array<string, string|null>
     */
    private function flowImage(VersionContent $content, DocumentVersion $target): array
    {
        $disk = Storage::disk(config('dcs.disk'));

        if ($content->flow_image_key === null || ! $disk->exists($content->flow_image_key)) {
            return ['flow_image_key' => null, 'flow_image_mime' => null];
        }

        $key = sprintf('tenant-%d/versions/%s/flow-%s.%s', $target->tenant_id, $target->uuid, Str::lower((string) Str::ulid()), pathinfo($content->flow_image_key, PATHINFO_EXTENSION) ?: 'png');
        $disk->put($key, (string) $disk->get($content->flow_image_key));

        return ['flow_image_key' => $key, 'flow_image_mime' => $content->flow_image_mime];
    }

    private function copySections(DocumentVersion $source, DocumentVersion $target): void
    {
        foreach (VersionSection::query()->where('version_id', $source->id)->orderBy('position')->get() as $section) {
            VersionSection::query()->forceCreate([
                'version_id' => $target->id,
                'position' => $section->position,
                'heading' => $section->heading,
                'content' => $section->content,
                'table_data' => $section->table_data,
            ]);
        }
    }

    private function copyAttachments(DocumentVersion $source, DocumentVersion $target): void
    {
        foreach (VersionAttachment::query()->where('version_id', $source->id)->orderBy('position')->get() as $attachment) {
            VersionAttachment::query()->forceCreate([
                'version_id' => $target->id,
                'position' => $attachment->position,
                'label' => $attachment->label,
                'url' => $attachment->url,
            ]);
        }
    }

    private function copyClauses(DocumentVersion $source, DocumentVersion $target): void
    {
        $rows = DB::table('dcs_version_clauses')->where('version_id', $source->id)->orderBy('position')->get(['clause_id', 'position']);

        foreach ($rows as $row) {
            DB::table('dcs_version_clauses')->insert(['version_id' => $target->id, 'clause_id' => $row->clause_id, 'position' => $row->position]);
        }
    }

    private function copySteps(DocumentVersion $source, DocumentVersion $target): void
    {
        foreach (VersionStep::query()->where('version_id', $source->id)->orderBy('group_position')->orderBy('position')->get() as $step) {
            VersionStep::query()->forceCreate([
                'version_id' => $target->id,
                'group_position' => $step->group_position,
                'group_heading' => $step->group_heading,
                'position' => $step->position,
                'label' => $step->label,
                'role_id' => $step->role_id,
                'role_name' => $step->role_name,
                'assigned_user_id' => $step->assigned_user_id,
                'fill_mode' => $step->fill_mode,
                'allow_creator' => $step->allow_creator,
                'status' => 'pending',
            ]);
        }
    }
}
