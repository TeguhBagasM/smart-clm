<?php

namespace App\Support\Comparison\Extraction;

use App\Models\AiTools\DocumentComparison;
use App\Support\Ai\InvalidAiOutput;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmManager;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\StructuredGenerator;

class LlmOcrEngine implements OcrEngine
{
    public function __construct(private readonly StructuredGenerator $generator, private readonly LlmManager $llm) {}

    public function read(array $images, ?DocumentComparison $comparison = null): array
    {
        if (! $this->llm->vision()->acceptsFiles()) {
            throw new ExtractionException('Penyedia AI yang dipilih tidak dapat membaca dokumen hasil scan.');
        }

        $texts = [];

        foreach (array_chunk($images, max(1, (int) config('ai.comparison.ocr_pages_per_call')), true) as $chunk) {
            $pages = array_keys($chunk);

            $request = new LlmRequest(
                prompt: $this->prompt(count($pages)),
                system: 'Anda adalah mesin OCR untuk dokumen hukum berbahasa Indonesia. Anda menyalin teks apa adanya, tidak merangkum, tidak menerjemahkan, dan tidak menambah isi.',
                files: array_map(fn (string $image): array => ['mime' => 'image/jpeg', 'data' => $image], array_values($chunk)),
                temperature: 0.0,
            );

            try {
                $reply = $this->generator->generate('ocr', $request, [
                    'pages' => ['required', 'array', 'size:'.count($pages)],
                    'pages.*.page' => ['required', 'integer', 'min:1', 'max:'.count($pages)],
                    'pages.*.text' => ['present', 'string'],
                ], $comparison, vision: true);
            } catch (InvalidAiOutput|LlmException $e) {
                throw new ExtractionException('OCR gagal membaca halaman '.min($pages).'-'.max($pages).': '.$e->getMessage());
            }

            foreach ($reply['pages'] as $entry) {
                $texts[$pages[$entry['page'] - 1]] = trim($entry['text']);
            }
        }

        return $texts;
    }

    private function prompt(int $count): string
    {
        return <<<PROMPT
Berikut {$count} gambar halaman dokumen, berurutan. Salin SELURUH teks tiap halaman persis seperti tertulis:
- pertahankan nomor Pasal, ayat, huruf, angka dan tanda baca;
- abaikan watermark, stempel, dan nomor halaman;
- tabel ditulis baris demi baris dengan sel dipisah " | ";
- bagian yang tidak terbaca ditulis [tidak terbaca].

Balas HANYA JSON: {"pages":[{"page":1,"text":"..."}, ...]} dengan "page" = urutan gambar (1..{$count}).
PROMPT;
    }
}
