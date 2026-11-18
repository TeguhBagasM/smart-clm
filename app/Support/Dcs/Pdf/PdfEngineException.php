<?php

namespace App\Support\Dcs\Pdf;

use RuntimeException;

class PdfEngineException extends RuntimeException
{
    /** True when the engine's address or secret is not set, as opposed to a failed call. */
    public bool $notConfigured = false;

    public static function notConfigured(): self
    {
        $e = new self('The PDF engine is not configured (DCS_PDF_ENGINE_URL and DCS_PDF_ENGINE_SECRET).');
        $e->notConfigured = true;

        return $e;
    }
}
