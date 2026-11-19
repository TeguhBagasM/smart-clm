<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient implements LlmClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout = 120,
    ) {}

    public function acceptsFiles(): bool
    {
        return true;
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        if (blank($this->apiKey)) {
            throw new LlmException('Layanan AI belum dikonfigurasi di server. Hubungi administrator.');
        }

        $parts = [];
        foreach ($request->files as $file) {
            $parts[] = ['inlineData' => ['mimeType' => $file['mime'], 'data' => base64_encode($file['data'])]];
        }
        $parts[] = ['text' => $request->prompt];

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => array_filter([
                'temperature' => $request->temperature,
                'responseMimeType' => $request->json ? 'application/json' : null,
            ], fn ($value) => $value !== null),
        ];

        if ($request->system !== null) {
            $body['systemInstruction'] = ['parts' => [['text' => $request->system]]];
        }

        $started = hrtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent", $body);
        } catch (ConnectionException $e) {
            throw new LlmException('Tidak dapat terhubung ke layanan AI. Coba lagi beberapa saat lagi.', true, $e->getMessage());
        }

        $this->guard($response);

        $candidate = $response->json('candidates.0');
        $text = implode('', array_filter(array_column($candidate['content']['parts'] ?? [], 'text')));

        if ($text === '') {
            throw new LlmException('Layanan AI tidak memberi jawaban. Coba lagi.', true, 'empty candidate: '.($candidate['finishReason'] ?? 'unknown'));
        }

        return new LlmResponse(
            text: $text,
            provider: 'gemini',
            model: $this->model,
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
            durationMs: (int) round((hrtime(true) - $started) / 1e6),
            truncated: ($candidate['finishReason'] ?? '') === 'MAX_TOKENS',
        );
    }

    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Gemini request failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        throw match (true) {
            $response->status() === 429 => new LlmException('Layanan AI sedang sibuk atau kuota habis. Coba lagi nanti.', true, (string) $response->status()),
            $response->status() >= 500 => new LlmException('Layanan AI sedang bermasalah. Coba lagi nanti.', true, (string) $response->status()),
            default => new LlmException('Permintaan ke layanan AI ditolak.', false, (string) $response->status()),
        };
    }
}
