<?php

namespace App\Support\Dcs\Ai;

/**
 * The AI services DCS uses. They run through the shared LLM layer (LlmDcsAi); the
 * interface keeps the actions independent of the provider and lets tests bind a fake.
 */
interface DcsAi
{
    /**
     * Translates a composed document from Indonesian to English, keeping its shape.
     *
     * @param  array{title: string, purpose: string, scope: string, sections: array<int, array<string, mixed>>}  $source
     * @return array<string, mixed> title, purpose, scope, sections as the model returned them
     *
     * @throws AiException
     */
    public function translate(array $source): array;

    /**
     * Reads an uploaded document and proposes its registration data.
     *
     * @param  array<int, string>  $docTypeCodes  the codes the answer must choose from
     * @return array<string, mixed> title, docTypeCode, department as the model returned them
     *
     * @throws AiException
     */
    public function extractMetadata(string $bytes, string $mimeType, array $docTypeCodes): array;
}
