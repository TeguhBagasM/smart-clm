<?php

namespace App\Enums\AiTools;

/**
 * How the two sides of a row were matched.
 */
enum Pairing: string
{
    case Number = 'number';
    case Similar = 'similar';
    case Llm = 'llm';
    case None = 'none';
}
