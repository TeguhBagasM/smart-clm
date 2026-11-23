<?php

namespace App\Support\Dcs\Ai;

use App\Support\Ai\InvalidAiOutput;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\StructuredGenerator;

/**
 * DCS AI through the shared LLM layer (Gemini or DeepSeek, chosen by config/ai.php). The prompts are
 * the ones the Node application used. Failures become AiException with the statuses Node answered:
 * 503 busy, 429 quota, 502 connection, 400 unreadable PDF, 500 generic fallback.
 * Document content is only sent to the model; it is never logged or stored in `ai_calls`.
 */
class LlmDcsAi implements DcsAi
{
    private const TRANSLATE_FALLBACK = 'Gagal menerjemahkan dokumen.';

    private const EXTRACT_FALLBACK = 'Gagal membaca dokumen via OCR AI.';

    private const SECTION_COUNT_PROBLEM = 'Jumlah bagian terjemahan harus sama dengan sumber.';

    public function __construct(private StructuredGenerator $generator) {}

    public function translate(array $source): array
    {
        $json = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $expected = count($source['sections'] ?? []);

        $prompt = <<<PROMPT
Anda adalah Penerjemah Tersumpah (Sworn Translator) spesialis dokumen kontrol mutu ISO 9001 (SOP/Instruksi Kerja/Kebijakan/Memo) dari Bahasa Indonesia ke Bahasa Inggris.
        Terjemahkan seluruh isi dokumen berikut ke Bahasa Inggris secara formal dan presisi, pertahankan struktur JSON-nya persis (jumlah dan urutan "sections" harus sama).

        === DOKUMEN SUMBER (JSON) ===
        {$json}
        === AKHIR DOKUMEN ===

        Balas HANYA dengan JSON berbentuk persis seperti sumber:
        { "title": "...", "purpose": "...", "scope": "...", "sections": [ { "heading": "...", "content": "..." } ] }
PROMPT;

        try {
            return $this->generator->generate('dcs_translate', new LlmRequest($prompt), [
                'title' => ['nullable', 'string'],
                'purpose' => ['nullable', 'string'],
                'scope' => ['nullable', 'string'],
                'sections' => ['present', 'array'],
                'sections.*.heading' => ['nullable', 'string'],
                'sections.*.content' => ['nullable', 'string'],
                'sections.*.table' => ['nullable', 'array'],
            ], check: fn (array $data): array => count($data['sections'] ?? []) === $expected ? [] : [self::SECTION_COUNT_PROBLEM]);
        } catch (LlmException $e) {
            throw $this->fromLlm($e, self::TRANSLATE_FALLBACK);
        } catch (InvalidAiOutput $e) {
            report($e);

            throw in_array(self::SECTION_COUNT_PROBLEM, $e->problems, true)
                ? new AiException(502, 'Hasil terjemahan tidak lengkap (jumlah bagian berbeda dari sumber). Coba lagi.')
                : new AiException(500, self::TRANSLATE_FALLBACK);
        }
    }

    public function extractMetadata(string $bytes, string $mimeType, array $docTypeCodes): array
    {
        $codes = json_encode(array_values($docTypeCodes), JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Anda adalah asisten Document Control ISO 9001. Baca dokumen ini (OCR bila hasil scan), lalu usulkan data untuk mendaftarkannya sebagai dokumen terkontrol.

Pilih docTypeCode dari daftar berikut PERSIS seperti tertulis (jangan mengarang kode di luar daftar; jika tidak yakin pilih yang paling mendekati jenis dokumennya):
- docTypeCode: {$codes}

Balas JSON:
{
  "title": "judul dokumen sesuai isi",
  "docTypeCode": "salah satu dari daftar docTypeCode",
  "department": "nama departemen/divisi pemilik dokumen jika terlihat, atau null"
}
PROMPT;

        try {
            return $this->generator->generate('dcs_extract_metadata', new LlmRequest($prompt, files: [['mime' => $mimeType, 'data' => $bytes]]), [
                'title' => ['required', 'string'],
                'docTypeCode' => ['nullable', 'string'],
                'department' => ['nullable', 'string'],
            ], vision: true);
        } catch (LlmException $e) {
            throw $this->fromLlm($e, self::EXTRACT_FALLBACK, unreadableFile: true);
        } catch (InvalidAiOutput $e) {
            report($e);

            throw new AiException(500, self::EXTRACT_FALLBACK);
        }
    }

    /**
     * LlmException carries no HTTP status; its `detail` is the vendor's status code for HTTP failures
     * and free text for connection problems or empty answers.
     */
    private function fromLlm(LlmException $e, string $fallback, bool $unreadableFile = false): AiException
    {
        $detail = $e->detail ?? '';

        if (ctype_digit($detail)) {
            $status = (int) $detail;

            if ($status === 429) {
                return new AiException(429, $e->getMessage());
            }

            if ($status >= 500) {
                return new AiException(503, $e->getMessage());
            }

            if ($status === 400 && $unreadableFile) {
                return new AiException(400, 'Berkas PDF tidak valid / kosong — pastikan file PDF berisi halaman.');
            }
        } elseif ($e->retryable && ! str_starts_with($detail, 'empty ')) {
            return new AiException(502, 'Koneksi ke layanan AI terputus. Periksa jaringan lalu coba lagi.');
        }

        report($e);

        return new AiException(500, $fallback);
    }
}
