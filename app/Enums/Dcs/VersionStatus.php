<?php

namespace App\Enums\Dcs;

/**
 * Status of a document version. The transition table is the one of the Node
 * state machine: anything not listed is refused.
 */
enum VersionStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Effective = 'effective';
    case Superseded = 'superseded';

    /**
     * The name shown to people (Indonesian, as in the interface).
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::UnderReview => 'Menunggu tanda tangan',
            self::Approved => 'Disetujui',
            self::Effective => 'Berlaku',
            self::Superseded => 'Tidak berlaku',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::UnderReview],
            self::UnderReview => [self::Approved, self::Draft],
            self::Approved => [self::Effective, self::UnderReview],
            self::Effective => [self::Superseded],
            self::Superseded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
