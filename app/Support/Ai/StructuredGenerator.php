<?php

namespace App\Support\Ai;

use App\Models\AiCall;
use App\Models\AiTools\DocumentComparison;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Asks the model for JSON with a fixed schema and refuses anything else. The reply is validated
 * with Laravel rules and an optional semantic check (e.g. "every id exists in the input"); on
 * failure the model gets the problems once more, then InvalidAiOutput is thrown. Every attempt is
 * logged and stored in `ai_calls` (provider, model, duration, tokens).
 */
class StructuredGenerator
{
    public function __construct(private readonly LlmManager $llm) {}

    /**
     * @param  array<string, mixed>  $rules  Laravel validation rules for the decoded reply
     * @param  Closure(array<mixed>): list<string>|null  $check  returns problems found in a schema-valid reply
     * @return array<mixed>
     *
     * @throws InvalidAiOutput
     * @throws LlmException
     */
    public function generate(string $stage, LlmRequest $request, array $rules, ?DocumentComparison $comparison = null, ?Closure $check = null, bool $vision = false): array
    {
        $client = $vision ? $this->llm->vision() : $this->llm->driver();
        $problems = [];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $current = $attempt === 1 ? $request : new LlmRequest(
                prompt: $request->prompt."\n\nBalasan sebelumnya ditolak karena:\n- ".implode("\n- ", array_slice($problems, 0, 8))."\nKirim ulang HANYA JSON yang sesuai skema.",
                system: $request->system,
                json: $request->json,
                files: $request->files,
                temperature: 0.0,
            );

            try {
                $response = $client->generate($current);
            } catch (LlmException $e) {
                $this->record($stage, $comparison, null, false, $e->detail ?? $e->getMessage());

                if ($e->retryable && $attempt === 1) {
                    $problems = [$e->getMessage()];

                    continue;
                }

                throw $e;
            }

            $data = JsonRepair::decode($response->text);
            $problems = $this->problems($data, $rules, $check, $response->truncated);

            $this->record($stage, $comparison, $response, $problems === [], $problems === [] ? null : implode('; ', $problems));

            if ($problems === []) {
                return $data;
            }
        }

        Log::warning('AI output rejected', ['stage' => $stage, 'problems' => $problems]);

        throw new InvalidAiOutput('Hasil AI tidak sesuai format yang diharapkan setelah dicoba dua kali. Coba jalankan ulang perbandingan.', $problems);
    }

    /**
     * @param  array<mixed>|null  $data
     * @param  array<string, mixed>  $rules
     * @param  Closure(array<mixed>): list<string>|null  $check
     * @return list<string>
     */
    private function problems(?array $data, array $rules, ?Closure $check, bool $truncated): array
    {
        if ($data === null) {
            return [$truncated ? 'Balasan terpotong sebelum JSON selesai; ringkas isi.' : 'Balasan bukan JSON yang valid.'];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return array_values($validator->errors()->all());
        }

        return $check === null ? [] : $check($data);
    }

    private function record(string $stage, ?DocumentComparison $comparison, ?LlmResponse $response, bool $ok, ?string $error): void
    {
        $call = AiCall::create([
            'tenant_id' => $comparison?->tenant_id,
            'comparison_id' => $comparison?->id,
            'stage' => $stage,
            'provider' => $response->provider ?? (string) config('ai.provider'),
            'model' => $response->model ?? '-',
            'input_tokens' => $response->inputTokens ?? 0,
            'output_tokens' => $response->outputTokens ?? 0,
            'duration_ms' => $response->durationMs ?? 0,
            'ok' => $ok,
            'error' => $error === null ? null : mb_substr($error, 0, 500),
        ]);

        Log::info('ai.call', $call->only(['stage', 'provider', 'model', 'input_tokens', 'output_tokens', 'duration_ms', 'ok', 'comparison_id']));
    }
}
