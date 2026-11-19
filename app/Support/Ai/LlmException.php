<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * The model could not be reached or refused. The message is safe to show to a user (Indonesian,
 * no vendor details); `detail` is for the log only.
 */
class LlmException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
