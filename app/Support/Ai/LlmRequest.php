<?php

namespace App\Support\Ai;

final readonly class LlmRequest
{
    /**
     * @param  list<array{mime: string, data: string}>  $files  raw bytes, base64 encoded by the client
     */
    public function __construct(
        public string $prompt,
        public ?string $system = null,
        public bool $json = true,
        public array $files = [],
        public float $temperature = 0.1,
    ) {}
}
