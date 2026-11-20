<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ai\JsonRepair;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmManager;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\LlmResponse;
use App\Support\Templates\ClauseDraftPrompt;
use App\Support\Templates\PlaceholderGuard;
use App\Support\Templates\Placeholders;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Suggests a clause from a plain description. It saves nothing: the person reads
 * the suggestion, edits it, and saves it through SaveContractClause. Needs the
 * right to create clauses. Whatever the model returns is checked: JSON with the
 * fixed keys, plain text, placeholders in the company's allowed set. Problems
 * come back as warnings the person sees. The audit trail keeps the length of the
 * description and the call's numbers, never its text.
 */
class DraftClauseWithAi
{
    private const ATTEMPTS = 2;

    public function __construct(private LlmManager $llm, private RecordAuditLog $recordAuditLog) {}

    /**
     * @return array{title: string, body: string, reasoning: string|null, warnings: list<string>}
     */
    public function handle(User $actor, Tenant $tenant, string $description): array
    {
        Gate::forUser($actor)->authorize('create', ContractClause::class);

        if ($actor->tenant_id !== null && $actor->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(ContractClause::class);
        }

        $description = ClauseDraftPrompt::clean($description);
        $messages = config()->array('contract_templates.messages');
        $limit = config()->integer('contract_templates.ai.max_description');

        if ($description === '') {
            throw ValidationException::withMessages(['description' => $messages['ai_description_required']]);
        }

        if (mb_strlen($description) > $limit) {
            throw ValidationException::withMessages(['description' => strtr($messages['ai_description_long'], [':max' => (string) $limit])]);
        }

        [$draft, $response] = $this->ask($description);
        $body = trim((string) $draft['body']);
        $warnings = [];

        if (preg_match('/<\/?[a-zA-Z][^>]*>/', $body) === 1) {
            $body = trim((string) preg_replace('/<\/?[a-zA-Z][^>]*>/', '', $body));
            $warnings[] = $messages['ai_html_removed'];
        }

        foreach (Placeholders::invalidTokens($body) as $token) {
            $warnings[] = strtr($messages['token_invalid'], [':token' => $token]);
        }

        if (Placeholders::hasStrayBraces($body)) {
            $warnings[] = $messages['token_stray'];
        }

        $known = PlaceholderGuard::known($this->companyCodes($tenant));

        foreach (array_diff(Placeholders::codes($body), $known) as $code) {
            $warnings[] = strtr($messages['ai_placeholder_unknown'], [':code' => '{{'.$code.'}}']);
        }

        $this->recordAuditLog->handle($actor, 'contract_templates.clauses.ai_drafted', $tenant, null, [
            'description_chars' => mb_strlen($description), 'provider' => $response->provider, 'model' => $response->model,
            'input_tokens' => $response->inputTokens, 'output_tokens' => $response->outputTokens, 'warnings' => count($warnings),
        ], $tenant->id);

        return [
            'title' => trim(strip_tags((string) $draft['title'])),
            'body' => $body,
            'reasoning' => isset($draft['reasoning']) && is_string($draft['reasoning']) ? trim($draft['reasoning']) : null,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Asks the model up to ATTEMPTS times for a reply that has the fixed keys.
     *
     * @return array{0: array<string, mixed>, 1: LlmResponse}
     */
    private function ask(string $description): array
    {
        $client = $this->llm->driver();
        $messages = config()->array('contract_templates.messages');
        $rules = ['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:'.config()->integer('contract_templates.limits.clause_body')], 'reasoning' => ['nullable', 'string', 'max:'.config()->integer('contract_templates.ai.max_reasoning')]];
        $prompt = ClauseDraftPrompt::user($description);

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $request = new LlmRequest(
                prompt: $attempt === 1 ? $prompt : $prompt."\n\nBalasan sebelumnya tidak valid. Kirim ulang HANYA JSON dengan kunci title, body, dan reasoning.",
                system: ClauseDraftPrompt::system(),
                temperature: 0.0,
            );

            try {
                $response = $client->generate($request);
            } catch (LlmException $exception) {
                Log::warning('ai.clause_draft.unavailable', ['detail' => $exception->detail]);

                throw ValidationException::withMessages(['description' => $messages['ai_unavailable']]);
            }

            $data = JsonRepair::decode($response->text);

            if ($data !== null && ! $response->truncated && Validator::make($data, $rules)->passes()) {
                return [$data, $response];
            }
        }

        Log::warning('ai.clause_draft.invalid', ['attempts' => self::ATTEMPTS]);

        throw ValidationException::withMessages(['description' => $messages['ai_invalid']]);
    }

    /**
     * @return list<string>
     */
    private function companyCodes(Tenant $tenant): array
    {
        $codes = [];

        foreach (ContractVariable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get() as $variable) {
            $codes[] = $variable->code;
        }

        return $codes;
    }
}
