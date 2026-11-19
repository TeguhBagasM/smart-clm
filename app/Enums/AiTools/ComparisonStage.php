<?php

namespace App\Enums\AiTools;

enum ComparisonStage: string
{
    case Extracting = 'extracting';
    case Splitting = 'splitting';
    case Aligning = 'aligning';
    case Diffing = 'diffing';
    case Analyzing = 'analyzing';
    case Summarizing = 'summarizing';

    public function label(): string
    {
        return match ($this) {
            self::Extracting => 'Membaca teks dokumen (ekstraksi/OCR)',
            self::Splitting => 'Memecah dokumen per pasal',
            self::Aligning => 'Memasangkan pasal yang berpadanan',
            self::Diffing => 'Menghitung perbedaan teks',
            self::Analyzing => 'Menganalisis perubahan per pasal',
            self::Summarizing => 'Menyusun tinjauan strategis',
        };
    }

    /**
     * Progress (0-100) shown once the stage has finished.
     */
    public function progressAfter(): int
    {
        return match ($this) {
            self::Extracting => 30,
            self::Splitting => 40,
            self::Aligning => 55,
            self::Diffing => 65,
            self::Analyzing => 90,
            self::Summarizing => 100,
        };
    }
}
