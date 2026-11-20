<?php

namespace App\Support\Templates;

use Illuminate\Validation\ValidationException;

/**
 * Text the library stores is plain text only (audit Q-T1): anything that looks
 * like an HTML tag is refused, and so is text over its length limit.
 */
class PlainText
{
    public static function check(string $text, string $field, int $max): void
    {
        if (preg_match('/<\/?[a-zA-Z][^>]*>/', $text) === 1) {
            throw ValidationException::withMessages([$field => config()->string('contract_templates.messages.body_html')]);
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => strtr(config()->string('contract_templates.messages.too_long'), [':attribute' => $field, ':max' => (string) $max])]);
        }
    }
}
