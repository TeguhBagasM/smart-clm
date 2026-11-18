<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores PDF bytes on the private DCS disk and records them as a new rendition
 * of the version, with their SHA-256. Existing renditions are never replaced.
 */
class StoreRendition
{
    public function handle(User $actor, DocumentVersion $version, RenditionKind $kind, string $bytes, ?int $stepPosition = null, ?string $language = null): Rendition
    {
        $disk = Storage::disk(config('dcs.disk'));
        $key = sprintf('tenant-%d/versions/%s/%s-%s.pdf', $version->tenant_id, $version->uuid, $kind->value, Str::lower((string) Str::ulid()));

        $disk->put($key, $bytes);

        try {
            $rendition = new Rendition;

            $rendition->forceFill([
                'version_id' => $version->id,
                'kind' => $kind,
                'step_position' => $stepPosition,
                'language' => $language,
                'storage_key' => $key,
                'sha256' => hash('sha256', $bytes),
                'size_bytes' => strlen($bytes),
                'mime_type' => 'application/pdf',
                'created_by_id' => $actor->id,
            ])->save();

            return $rendition;
        } catch (Throwable $e) {
            $disk->delete($key);

            throw $e;
        }
    }
}
