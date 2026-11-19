<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek speaks the OpenAI chat protocol. It reads text only, so files are refused.
 */
class DeepSeekClient implements LlmClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout = 120,
    ) {}

    public function acceptsFiles(): bool
    {
        return false;
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        if ($request->files !== []) {
            throw new LlmException('Penyedia AI ini tidak dapat membaca berkas.');
        }

        if (blank($this->apiKey)) {
            throw new LlmException('Layanan AI belum dikonfigurasi di server. Hubungi administrator.');
        }

        $messages = array_values(array_filter([
            $request->system !== null ? ['role' => 'system', 'content' => $request->system] : null,
            ['role' => 'user', 'content' => $request->prompt],
        ]));

        $body = ['model' => $this->model, 'messages' => $messages, 'temperature' => $request->temperature];

        if ($request->json) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $started = hrtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw new LlmException('Tidak dapat terhubung ke layanan AI. Coba lagi beberapa saat lagi.', true, $e->getMessage());
        }

        $this->guard($response);

        $text = (string) $response->json('choices.0.message.content', '');

        if ($text === '') {
            throw new LlmException('Layanan AI tidak memberi jawaban. Coba lagi.', true, 'empty message');
        }

        return new LlmResponse(
            text: $text,
            provider: 'deepseek',
            model: $this->model,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            durationMs: (int) round((hrtime(true) - $started) / 1e6),
            truncated: $response->json('choices.0.finish_reason') === 'length',
        );
    }

    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('DeepSeek request failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        throw match (true) {
            $response->status() === 429 => new LlmException('Layanan AI sedang sibuk atau kuota habis. Coba lagi nanti.', true, (string) $response->status()),
            $response->status() >= 500 => new LlmException('Layanan AI sedang bermasalah. Coba lagi nanti.', true, (string) $response->status()),
            default => new LlmException('Permintaan ke layanan AI ditolak.', false, (string) $response->status()),
        };
    }
}
