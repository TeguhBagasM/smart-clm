<?php

namespace App\Enums\AiTools;

enum ChangeKind: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Changed = 'changed';
    case Unchanged = 'unchanged';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Norma Baru',
            self::Removed => 'Dihapus Total',
            self::Changed => 'Diubah',
            self::Unchanged => 'Tetap',
        };
    }

    /**
     * Order in which rows are offered to the strategic review (new norms first, unchanged last).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Added => 0,
            self::Removed => 1,
            self::Changed => 2,
            self::Unchanged => 3,
        };
    }
}
