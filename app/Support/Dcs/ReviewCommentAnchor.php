<?php

namespace App\Support\Dcs;

use Illuminate\Support\Str;

/**
 * Cleans up where a review comment points: a part of the content (purpose,
 * scope, one section, the flow, or the document in general) and optionally the
 * quoted text. Anything unrecognised becomes "general", as in the Node API.
 */
class ReviewCommentAnchor
{
    /**
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['field' => 'general'];
        }

        $field = in_array($raw['field'] ?? null, ['purpose', 'scope', 'section', 'flow', 'general'], true) ? $raw['field'] : 'general';
        $anchor = ['field' => $field];

        if ($field === 'section' && isset($raw['sectionIndex']) && is_numeric($raw['sectionIndex']) && (int) $raw['sectionIndex'] == $raw['sectionIndex']) {
            $anchor['sectionIndex'] = (int) $raw['sectionIndex'];
        }

        if (isset($raw['sectionHeading']) && is_string($raw['sectionHeading'])) {
            $anchor['sectionHeading'] = Str::substr($raw['sectionHeading'], 0, 200);
        }

        if (isset($raw['quote']) && is_string($raw['quote']) && trim($raw['quote']) !== '') {
            $anchor['quote'] = Str::substr($raw['quote'], 0, 500);
        }

        return $anchor;
    }
}
