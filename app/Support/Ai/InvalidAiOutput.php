<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * The model answered twice without the JSON the schema asks for.
 */
class InvalidAiOutput extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
