<?php

namespace App\Support\Dcs\Pdf;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Composes documents through the Node application's internal endpoint
 * (POST /internal/dcs/pdf/compose), authenticated with a shared secret.
 * Binary parts travel as base64.
 */
class NodePdfEngine implements PdfEngine
{
    public function __construct(private ?string $baseUrl, private ?string $secret, private int $timeout = 120) {}

    public function compose(array $input): array
    {
        $response = $this->post('/internal/dcs/pdf/compose', $this->wirePayload($input));

        return [
            'pdf' => $this->decodePdf($response->json('pdfBase64')),
            'signatureSheetPageIndex' => $response->json('signatureSheetPageIndex'),
            'sigLayout' => $response->json('sigLayout'),
            'headerLayout' => $response->json('headerLayout'),
        ];
    }

    public function stampSignature(array $input): string
    {
        $input['pdfBase64'] = base64_encode($input['pdf']);
        unset($input['pdf']);

        return $this->decodePdf($this->post('/internal/dcs/pdf/sign', $input)->json('pdfBase64'));
    }

    public function watermark(array $input): string
    {
        $input['pdfBase64'] = base64_encode($input['pdf']);
        unset($input['pdf']);

        return $this->decodePdf($this->post('/internal/dcs/pdf/watermark', $input)->json('pdfBase64'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        if ($this->baseUrl === null || $this->baseUrl === '' || $this->secret === null || $this->secret === '') {
            throw new PdfEngineException('The PDF engine is not configured (DCS_PDF_ENGINE_URL and DCS_PDF_ENGINE_SECRET).');
        }

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['X-Internal-Secret' => $this->secret])
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new PdfEngineException('The PDF engine cannot be reached: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new PdfEngineException("The PDF engine answered {$response->status()}: ".($response->json('error') ?? $response->body()));
        }

        return $response;
    }

    private function decodePdf(mixed $base64): string
    {
        $pdf = base64_decode((string) $base64, true);

        if ($pdf === false || ! str_starts_with($pdf, '%PDF')) {
            throw new PdfEngineException('The PDF engine did not return a PDF.');
        }

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function wirePayload(array $input): array
    {
        if (($input['flow']['mode'] ?? null) === 'image') {
            $input['flow'] = ['mode' => 'image', 'imageBase64' => base64_encode($input['flow']['image']), 'mimeType' => $input['flow']['mimeType']];
        }

        if (is_array($input['letterhead'] ?? null) && isset($input['letterhead']['logo'])) {
            $input['letterhead']['logoBase64'] = base64_encode($input['letterhead']['logo']);
            unset($input['letterhead']['logo']);
        }

        return $input;
    }
}
