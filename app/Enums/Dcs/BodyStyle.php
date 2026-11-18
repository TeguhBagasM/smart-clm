<?php

namespace App\Enums\Dcs;

/**
 * How the body of a composed document is typeset. Formal is the Indonesian SOP
 * look: Times, roman-numbered headings, justified text.
 */
enum BodyStyle: string
{
    case Standard = 'standard';
    case Formal = 'formal';
}
