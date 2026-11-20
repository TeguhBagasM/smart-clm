<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/** Page geometry of a composed document (A4, margins in points, the content box under the header band). */
final class Layout
{
    public const PAGE_W = 595.28;

    public const PAGE_H = 841.89;

    public const DEFAULT_MARGIN = 56;

    public const HEADER_BOX_H = 96;

    private const HEADER_GAP = 18;

    public function __construct(
        public readonly float $mLeft,
        public readonly float $mRight,
        public readonly float $mTop,
        public readonly float $mBottom,
        public readonly float $contentW,
        public readonly float $contentTopY,
        public readonly bool $iso,
    ) {}

    /** @param  array<string, mixed>|null  $margins */
    public static function build(?array $margins, bool $iso, float $headerHeight): self
    {
        $left = $margins['left'] ?? self::DEFAULT_MARGIN;
        $right = $margins['right'] ?? self::DEFAULT_MARGIN;
        $top = $margins['top'] ?? self::DEFAULT_MARGIN;
        $bottom = $margins['bottom'] ?? self::DEFAULT_MARGIN;

        return new self($left, $right, $top, $bottom, self::PAGE_W - $left - $right, self::PAGE_H - $top - ($iso ? $headerHeight + self::HEADER_GAP : 0), $iso);
    }
}
