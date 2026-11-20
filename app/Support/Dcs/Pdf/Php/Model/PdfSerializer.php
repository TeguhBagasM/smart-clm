<?php

namespace App\Support\Dcs\Pdf\Php\Model;

use RuntimeException;

/** Writes the model back as PDF syntax. */
final class PdfSerializer
{
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            $value instanceof Name => '/'.preg_replace_callback('/[^!-~]|[#\/()<>\[\]{}%]/', fn (array $m): string => sprintf('#%02X', ord($m[0])), $value->value),
            $value instanceof Ref => "{$value->number} {$value->generation} R",
            $value instanceof PdfString => $value->hex ? '<'.bin2hex($value->bytes).'>' : '('.addcslashes($value->bytes, "\\()\r").')',
            $value instanceof Dict => '<<'.implode(' ', array_map(fn (string $k, mixed $v): string => self::value(new Name($k)).' '.self::value($v), array_keys($value->items), $value->items)).'>>',
            is_array($value) => '['.implode(' ', array_map(self::value(...), $value)).']',
            default => throw new RuntimeException('Cannot serialize '.get_debug_type($value)),
        };
    }

    /** Numbers as pdf-lib/JavaScript print them: shortest round-trip form, never an exponent. */
    public static function number(int|float $number): string
    {
        if (is_int($number) || floor($number) === $number && abs($number) < 1e15) {
            return (string) (int) $number;
        }

        $text = json_encode($number);

        return str_contains($text, 'e') || str_contains($text, 'E') ? rtrim(rtrim(sprintf('%.10F', $number), '0'), '.') : $text;
    }

    public static function object(int $number, int $generation, mixed $value): string
    {
        if ($value instanceof PdfStream) {
            $dict = new Dict(['Length' => strlen($value->data)] + array_diff_key($value->dict->items, ['Length' => 1]));

            return "{$number} {$generation} obj\n".self::value($dict)."\nstream\n{$value->data}\nendstream\nendobj\n";
        }

        return "{$number} {$generation} obj\n".self::value($value)."\nendobj\n";
    }
}
