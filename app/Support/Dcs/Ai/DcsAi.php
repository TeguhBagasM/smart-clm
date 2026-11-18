<?php

namespace App\Support\Dcs\Ai;

/**
 * The AI services DCS uses. Today they run in the Node application (which holds
 * the Gemini key and the prompts); the interface keeps the actions independent of that.
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
