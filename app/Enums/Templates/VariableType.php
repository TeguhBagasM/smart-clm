<?php

namespace App\Enums\Templates;

/**
 * What kind of value a template field holds (Node Variable.type:
 * "string" | "number" | "date", src/types.ts:118). Stored as a varchar guarded
 * by a CHECK on PostgreSQL.
 */
enum VariableType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Number => 'Angka',
            self::Date => 'Tanggal',
        };
    }

    /**
     * The type of a Node variable; anything unknown is text.
     */
    public static function fromNode(?string $type): self
    {
        return match ($type) {
            'number' => self::Number,
            'date' => self::Date,
            default => self::Text,
        };
    }
}
