<?php

namespace Tests\Support;

use App\Support\Dcs\Pdf\PhpPdfEngine;

/** Reads the golden files Node produced (tests/Fixtures/pdf-golden) and the payloads that made them. */
final class PdfGolden
{
    public static function root(): string
    {
        return dirname(__DIR__).'/Fixtures/pdf-golden';
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function cases(string $kind): array
    {
        $cases = [];

        foreach (glob(self::root()."/payloads/$kind/*.json") ?: [] as $file) {
            $cases[basename($file, '.json')] = [basename($file, '.json')];
        }

        return $cases;
    }

    /**
     * The payload of a case with its source PDF (raw bytes) under `pdf`.
     *
     * @return array<string, mixed>
     */
    public static function input(string $kind, string $id): array
    {
        $payload = json_decode(file_get_contents(self::root()."/payloads/$kind/$id.json"), true, flags: JSON_THROW_ON_ERROR);

        return ['pdf' => file_get_contents(self::root().'/'.$payload['input'])] + $payload;
    }

    /**
     * A compose payload with its binary parts (logo, flow image) read from the assets.
     *
     * @return array<string, mixed>
     */
    public static function composeInput(string $id): array
    {
        $payload = json_decode(file_get_contents(self::root()."/payloads/compose/$id.json"), true, flags: JSON_THROW_ON_ERROR);
        $resolve = function (mixed $value) use (&$resolve): mixed {
            if (is_array($value)) {
                return array_map($resolve, $value);
            }

            return is_string($value) && str_starts_with($value, '@asset:') ? file_get_contents(self::root().'/assets/'.substr($value, 7)) : $value;
        };

        return $resolve($payload);
    }

    /** What the Node engine answered for a compose case: golden PDF plus its sheet page index and layouts. */
    public static function composeMeta(string $id): array
    {
        return json_decode(file_get_contents(self::root()."/golden/compose/$id.meta.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function composeCases(): array
    {
        return array_filter(self::cases('compose'), fn (array $case): bool => is_file(self::root()."/golden/compose/{$case[0]}.pdf"));
    }

    public static function pdf(string $kind, string $id): string
    {
        return file_get_contents(self::root()."/golden/$kind/$id.pdf");
    }

    public static function engine(): PhpPdfEngine
    {
        return new PhpPdfEngine('Asia/Jakarta');
    }
}
