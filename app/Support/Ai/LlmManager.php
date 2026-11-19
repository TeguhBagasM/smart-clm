<?php

namespace App\Support\Ai;

use Closure;
use InvalidArgumentException;

/**
 * Builds the configured LlmClient. Feature code asks for `driver()` (text) or `vision()` (files)
 * and never names a vendor; tests register a fake with `extend()`.
 */
class LlmManager
{
    /** @var array<string, Closure(): LlmClient> */
    private array $custom = [];

    public function extend(string $name, Closure $resolver): void
    {
        $this->custom[$name] = $resolver;
    }

    public function driver(?string $name = null): LlmClient
    {
        $name ??= (string) config('ai.provider');

        if (isset($this->custom[$name])) {
            return ($this->custom[$name])();
        }

        $settings = config("ai.providers.{$name}");
        $timeout = (int) config('ai.timeout');

        return match ($name) {
            'gemini' => new GeminiClient($settings['api_key'], $settings['model'], $settings['base_url'], $timeout),
            'deepseek' => new DeepSeekClient($settings['api_key'], $settings['model'], $settings['base_url'], $timeout),
            default => throw new InvalidArgumentException("Penyedia AI \"{$name}\" tidak dikenal."),
        };
    }

    public function vision(): LlmClient
    {
        return $this->driver((string) config('ai.vision_provider'));
    }
}
