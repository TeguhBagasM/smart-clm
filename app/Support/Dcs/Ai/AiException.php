<?php

namespace App\Support\Dcs\Ai;

use RuntimeException;

/**
 * An AI call failed. `status` and the message are the user-facing ones the Node
 * side chose (busy, quota, connection, invalid file, or a generic fallback).
 */
class AiException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
