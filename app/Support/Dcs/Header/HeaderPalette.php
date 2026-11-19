<?php

namespace App\Support\Dcs\Header;

/**
 * The colours of the letterhead editor. The values the engine always drew
 * (dark band, muted fill, border, text) are constants here so the version 1
 * migration reproduces them exactly; the swatches are what the picker offers.
 * They live on the server because the frontend keeps colour literals out of Vue.
 */
final class HeaderPalette
{
    public const HEX_PATTERN = '/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/';

    public const COLOR_PATTERN = '/^(auto|#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?)$/';

    public const DARK = '#243661';

    public const MUTED = '#EBEDF2';

    public const BORDER = '#404552';

    public const TEXT = '#1F212B';

    public const WHITE = '#FFFFFF';

    /** @var list<string> */
    public const SWATCHES = [
        '#FFFFFF', '#EBEDF2', '#BFC5D2', '#6B7280', '#1F212B', '#243661', '#2563EB', '#60A5FA',
        '#0F766E', '#16A34A', '#CA8A04', '#F59E0B', '#EA580C', '#DC2626', '#9333EA', '#DB2777',
    ];

    /**
     * Upper-case hex, so equal colours compare equal.
     */
    public static function canonical(string $color): string
    {
        return strtolower($color) === 'auto' ? 'auto' : strtoupper($color);
    }

    /**
     * What the editor needs to draw and to offer.
     *
     * @return array{swatches: list<string>, border: string, text: string, light: string, dark: string}
     */
    public static function forEditor(): array
    {
        return ['swatches' => self::SWATCHES, 'border' => self::BORDER, 'text' => self::TEXT, 'light' => self::WHITE, 'dark' => self::TEXT];
    }
}
