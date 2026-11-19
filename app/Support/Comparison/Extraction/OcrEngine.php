<?php

namespace App\Support\Comparison\Extraction;

use App\Models\AiTools\DocumentComparison;

/**
 * Turns page images into text. The default reads them with a vision-capable language model; a
 * dedicated OCR service (e.g. a self-hosted model) can replace it by implementing this interface.
 */
interface OcrEngine
{
    /**
     * @param  array<int, string>  $images  page number => JPEG bytes
     * @return array<int, string> page number => text
     *
     * @throws ExtractionException
     */
    public function read(array $images, ?DocumentComparison $comparison = null): array;
}
