<?php

namespace App\Support\Templates;

use Illuminate\Validation\ValidationException;

/**
 * Refuses a text whose placeholders are malformed, or name a field that is not
 * allowed where the text is saved (audit A2.5). The system tokens of the contract
 * module are allowed everywhere.
 */
class PlaceholderGuard
{
    /**
     * @param  list<string>  $fieldCodes
     * @return list<string>
     */
    public static function known(array $fieldCodes): array
    {
        return array_values(array_unique([...$fieldCodes, ...array_keys(config()->array('contract_templates.system_tokens'))]));
    }

    /**
     * @param  list<string>  $allowed  codes that may appear in the text
     */
    public static function ensureUsable(string $text, array $allowed, string $field, string $unknownMessage): void
    {
        $messages = config()->array('contract_templates.messages');
        $problems = array_map(fn (string $token): string => strtr($messages['token_invalid'], [':token' => $token]), Placeholders::invalidTokens($text));

        if (Placeholders::hasStrayBraces($text)) {
            $problems[] = $messages['token_stray'];
        }

        if ($problems !== []) {
            throw ValidationException::withMessages([$field => implode(' ', array_unique($problems))]);
        }

        $unknown = array_values(array_diff(Placeholders::codes($text), $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages([$field => strtr($unknownMessage, [':code' => implode(', ', array_map(fn (string $code): string => '{{'.$code.'}}', $unknown))])]);
        }
    }
}
