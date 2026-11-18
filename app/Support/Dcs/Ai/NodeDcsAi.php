<?php

namespace App\Support\Dcs\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls the Node application's internal AI endpoints (/internal/dcs/ai/*),
 * guarded by the shared secret. An error answer keeps the status and the
 * message Node chose.
 */
class NodeDcsAi implements DcsAi
{
    public function __construct(private ?string $baseUrl, private ?string $secret, private int $timeout = 120) {}

    public function translate(array $source): array
    {
        $translated = $this->post('/internal/dcs/ai/translate', ['source' => $source], 'Gagal menerjemahkan dokumen.')->json('translated');

        return is_array($translated) ? $translated : throw new AiException(500, 'Gagal menerjemahkan dokumen.');
    }

    public function extractMetadata(string $bytes, string $mimeType, array $docTypeCodes): array
    {
        $result = $this->post('/internal/dcs/ai/extract-metadata', [
            'mimeType' => $mimeType, 'dataBase64' => base64_encode($bytes), 'docTypeCodes' => $docTypeCodes,
        ], 'Gagal membaca dokumen via OCR AI.')->json('result');

        return is_array($result) ? $result : throw new AiException(500, 'Gagal membaca dokumen via OCR AI.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload, string $fallback): Response
    {
        if ($this->baseUrl === null || $this->baseUrl === '' || $this->secret === null || $this->secret === '') {
            report(new AiException(500, 'The AI service is not configured (DCS_AI_URL and DCS_AI_SECRET).'));

            throw new AiException(500, $fallback);
        }

        try {
            $response = Http::baseUrl($this->baseUrl)->withHeaders(['X-Internal-Secret' => $this->secret])->timeout($this->timeout)->acceptJson()->asJson()->post($path, $payload);
        } catch (ConnectionException $e) {
            report($e);

            throw new AiException(502, 'Koneksi ke layanan AI terputus. Periksa jaringan lalu coba lagi.');
        }

        if ($response->successful()) {
            return $response;
        }

        if (in_array($response->status(), [400, 429, 502, 503], true) && is_string($response->json('error'))) {
            throw new AiException($response->status(), $response->json('error'));
        }

        report(new AiException($response->status(), "The AI service answered {$response->status()}: {$response->body()}"));

        throw new AiException(500, $fallback);
    }
}
