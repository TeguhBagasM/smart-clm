<?php

namespace App\Support\Comparison\Extraction;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Reads PDFs with poppler-utils (`pdfinfo`, `pdftotext`, `pdftoppm`), which must be installed on the
 * server. Paths are passed as separate arguments, never through a shell.
 */
class PopplerPdfReader implements PdfReader
{
    public function pageCount(string $path): int
    {
        $result = Process::timeout(30)->run([config('ai.comparison.pdfinfo_binary'), $path]);

        if ($result->failed() || ! preg_match('/^Pages:\s+(\d+)/m', $result->output(), $match)) {
            throw new ExtractionException('Berkas PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terkunci kata sandi.');
        }

        return (int) $match[1];
    }

    public function pageTexts(string $path): array
    {
        $result = Process::timeout(120)->run([config('ai.comparison.pdftotext_binary'), '-enc', 'UTF-8', $path, '-']);

        if ($result->failed()) {
            throw new ExtractionException('Teks PDF tidak dapat dibaca.');
        }

        $pages = explode("\f", $result->output());
        array_pop($pages);

        return array_map(fn (string $page): string => trim($page), $pages);
    }

    public function renderPages(string $path, array $pages): array
    {
        $directory = sys_get_temp_dir().'/cmp-'.Str::random(12);
        File::makeDirectory($directory, 0700, true);

        try {
            $images = [];

            foreach ($pages as $page) {
                $result = Process::timeout(60)->run([
                    config('ai.comparison.pdftoppm_binary'), '-f', (string) $page, '-l', (string) $page,
                    '-r', '130', '-jpeg', '-singlefile', $path, "{$directory}/p{$page}",
                ]);

                $file = "{$directory}/p{$page}.jpg";

                if ($result->failed() || ! is_file($file)) {
                    throw new ExtractionException("Halaman {$page} tidak dapat dirender untuk OCR.");
                }

                $images[$page] = (string) file_get_contents($file);
            }

            return $images;
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
