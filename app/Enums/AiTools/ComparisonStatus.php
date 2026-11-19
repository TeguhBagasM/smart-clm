<?php

namespace App\Enums\AiTools;

enum ComparisonStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu antrean',
            self::Processing => 'Sedang diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
