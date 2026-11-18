<?php

namespace App\Support\Dcs;

use App\Models\Dcs\DocumentVersion;

/**
 * Which wizard steps of a draft are filled in, so the client can show progress.
 * Pure: it reads the version's loaded content, sections and steps and decides
 * nothing about what may be saved or composed.
 */
class DraftCompleteness
{
    /** @var list<string> */
    public const STEPS = ['basic', 'purpose_scope', 'body', 'flow', 'attachments', 'signers'];

    /**
     * @return array<string, bool>
     */
    public static function of(DocumentVersion $version): array
    {
        $content = $version->content;

        $flowDone = match ($content?->flow_mode) {
            'builder' => $content->flow_steps !== [],
            'image' => $content->flow_image_key !== null,
            default => true,
        };

        return [
            'basic' => true,
            'purpose_scope' => $content !== null && trim((string) $content->purpose) !== '' && trim((string) $content->scope) !== '',
            'body' => $version->sections->contains(fn ($section): bool => trim($section->heading) !== '' || trim($section->content) !== ''),
            'flow' => $flowDone,
            'attachments' => true,
            'signers' => $version->steps->isNotEmpty() && $version->steps->every(fn ($step): bool => $step->assigned_user_id !== null),
        ];
    }
}
