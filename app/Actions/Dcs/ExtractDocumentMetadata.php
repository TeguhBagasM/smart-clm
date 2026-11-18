<?php

namespace App\Actions\Dcs;

use App\Models\Tenant;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\DcsException;

/**
 * Suggests the title, document type and department for an uploaded document
 * (the "Unggah Dokumen Jadi" path of the creation wizard). The model may only
 * choose among the tenant's real document types: an unknown code is dropped from
 * the answer, so the wizard never receives something it cannot select.
 */
class ExtractDocumentMetadata
{
    public function __construct(private DcsAi $dcsAi) {}

    /**
     * @return array<string, mixed>
     *
     * @throws DcsException
     */
    public function handle(Tenant $tenant, string $bytes, string $mimeType): array
    {
        $codes = $tenant->dcsDocumentTypes()->orderBy('code')->pluck('code')->all();

        try {
            $result = $this->dcsAi->extractMetadata($bytes, $mimeType, $codes);
        } catch (AiException $e) {
            throw new DcsException($e->status, $e->getMessage());
        }

        if (isset($result['docTypeCode']) && ! in_array($result['docTypeCode'], $codes, true)) {
            unset($result['docTypeCode']);
        }

        return $result;
    }
}
