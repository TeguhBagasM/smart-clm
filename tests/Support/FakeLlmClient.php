<?php

namespace Tests\Support;

use App\Support\Ai\LlmClient;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmManager;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\LlmResponse;
use Closure;

/**
 * Stands in for the language model: replies come from a queue of strings (or closures that get the
 * request) and every request is kept for assertions.
 */
class FakeLlmClient implements LlmClient
{
    /** @var list<string|Closure(LlmRequest): string> */
    private array $replies = [];

    /** @var list<LlmRequest> */
    public array $requests = [];

    public bool $files = true;

    /**
     * Registers a fake as the text and the vision provider.
     */
    public static function install(): self
    {
        $fake = new self;
        $manager = app(LlmManager::class);
        $manager->extend('fake', fn () => $fake);
        config(['ai.provider' => 'fake', 'ai.vision_provider' => 'fake']);

        return $fake;
    }

    /**
     * @param  string|array<mixed>|Closure(LlmRequest): (string|array<mixed>)  $reply
     */
    public function reply(string|array|Closure $reply): self
    {
        $this->replies[] = $reply instanceof Closure
            ? fn (LlmRequest $request) => $this->encode($reply($request))
            : $this->encode($reply);

        return $this;
    }

    public function acceptsFiles(): bool
    {
        return $this->files;
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        $reply = array_shift($this->replies) ?? throw new LlmException('FakeLlmClient: no reply queued');
        $text = $reply instanceof Closure ? $reply($request) : $reply;

        return new LlmResponse($text, 'fake', 'fake-1', 100, 50, 12);
    }

    /**
     * @param  string|array<mixed>  $reply
     */
    private function encode(string|array $reply): string
    {
        return is_array($reply) ? json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $reply;
    }
}
