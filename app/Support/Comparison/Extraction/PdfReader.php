<?php

namespace App\Support\Comparison\Extraction;

/**
 * Reads a PDF on disk: how many pages, the text layer of each page, and page images for OCR.
 */
interface PdfReader
{
    /**
     * @throws ExtractionException when the file is not a readable PDF
     */
    public function pageCount(string $path): int;

    /**
     * Text layer of every page, in page order ("" for a page without one).
     *
     * @return list<string>
     *
     * @throws ExtractionException
     */
    public function pageTexts(string $path): array;

    /**
     * JPEG bytes of the given 1-based pages.
     *
     * @param  list<int>  $pages
     * @return array<int, string> page number => image
     *
     * @throws ExtractionException
     */
    public function renderPages(string $path, array $pages): array;
}
