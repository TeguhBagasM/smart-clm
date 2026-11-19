<?php

namespace App\Support\Ai;

/**
 * The boundary to a language model. Features build an LlmRequest and read an LlmResponse;
 * which vendor answers is decided in config/ai.php, so Gemini and DeepSeek are swappable.
 */
interface LlmClient
{
    /**
     * @throws LlmException
     */
    public function generate(LlmRequest $request): LlmResponse;

    /**
     * Whether the model can read files (PDF pages, images) sent with the request.
     */
    public function acceptsFiles(): bool;
}
