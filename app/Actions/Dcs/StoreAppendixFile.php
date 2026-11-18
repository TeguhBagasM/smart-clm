<?php

namespace App\Actions\Dcs;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a Word or PDF appendix on the public disk and returns its URL. The
 * link is printed in the document, so it must open without logging in.
 */
class StoreAppendixFile
{
    /**
     * @return array{url: string, name: string}
     */
    public function handle(Tenant $tenant, UploadedFile $file): array
    {
        $safeName = Str::limit(preg_replace('/[^\w.\-]/', '_', $file->getClientOriginalName()) ?? 'lampiran', 100, '');
        $key = sprintf('dcs-appendix/tenant-%d/%s-%s', $tenant->id, Str::lower((string) Str::ulid()), $safeName);

        $disk = Storage::disk(config('dcs.public_disk'));
        $disk->put($key, (string) file_get_contents($file->getRealPath()));

        return ['url' => $disk->url($key), 'name' => $file->getClientOriginalName()];
    }
}
