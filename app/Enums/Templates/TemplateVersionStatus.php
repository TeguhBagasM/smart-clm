<?php

namespace App\Enums\Templates;

/**
 * Life of one version of a contract template. A draft is edited freely; a
 * published version is frozen (its text, clauses, parties and fields never
 * change again) and is what new contracts use; publishing a newer version
 * retires the one before. Stored as a varchar guarded by a CHECK on PostgreSQL.
 */
enum TemplateVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    /** @var array<string, list<string>> allowed moves: from => to */
    private const TRANSITIONS = [
        'draft' => ['published'],
        'published' => ['retired'],
        'retired' => [],
    ];

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
            self::Retired => 'Versi lama',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value], true);
    }

    /**
     * Whether the content of a version in this state may still change.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
