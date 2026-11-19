<?php

namespace App\Support\Ai;

final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $durationMs,
        public bool $truncated = false,
    ) {}
}
