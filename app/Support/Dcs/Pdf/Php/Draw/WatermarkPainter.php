<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;

/**
 * The reactive watermark of a viewed document (port of dcs/pdf-watermark.ts and
 * pdf-draw.ts): a diagonal banner, the provenance footer and, when the compose step
 * left room for them, the live "Status Dokumen" and "Tanggal Diterbitkan" header cells.
 * The banner, footer text, colours, opacities and positions are the contract the
 * Node engine set; the golden files in tests/Fixtures/pdf-golden pin them.
 */
final class WatermarkPainter
{
    private const RED = [0.80, 0.12, 0.12];

    public function __construct(private string $timezone) {}

    /**
     * @param  array<string, mixed>  $in  status, viewerName, accessedAt, documentNumber, major, minor, expired, headerLayout, issuedDateIso
     */
    public function paint(PdfFile $file, array $in): void
    {
        $bold = StandardFont::named('Helvetica-Bold');
        $regular = StandardFont::named('Helvetica');
        $spec = $this->spec($in);
        $layout = $in['headerLayout'] ?? null;
        [$statusLabel, $statusColor] = $this->headerStatus($in);
        $issued = IdDate::numeric($in['issuedDateIso'] ?? null, $this->timezone);
        $pool = new FontPool($file);

        foreach ($file->pages() as $page) {
            $canvas = new PageCanvas($file, $pool, $page['ref'], $page['width'], $page['height']);
            $this->banner($canvas, $bold, $spec['banner'], $spec['color'], $spec['bannerOpacity']);
            $canvas->text($spec['footer'], $bold, 7.5, 24, 14, $spec['color'], $spec['footerOpacity'], 0, $canvas->width - 48);

            if (is_array($layout)) {
                if (isset($layout['status'])) {
                    $canvas->text($statusLabel, $bold, $layout['status']['size'], $layout['status']['x'], $layout['status']['y'], $statusColor);
                }

                if (isset($layout['issued'])) {
                    $canvas->text($issued, $regular, $layout['issued']['size'], $layout['issued']['x'], $layout['issued']['y'], [0.2, 0.22, 0.27]);
                }
            }

            $canvas->flush();
        }
    }

    /** One banner per page: the text at 45 degrees, centred, never wider than 70% of the diagonal. */
    private function banner(PageCanvas $canvas, StandardFont $font, string $text, array $color, float $opacity): void
    {
        $size = min(96, hypot($canvas->width, $canvas->height) * 0.7 / $font->widthOfTextAtSize($text, 1));
        $width = $font->widthOfTextAtSize($text, $size);
        $lift = $font->ascentAtSize($size) / 2;
        [$cos, $sin] = [cos(M_PI / 4), sin(M_PI / 4)];

        $canvas->text(
            $text, $font, $size,
            $canvas->width / 2 - ($cos * ($width / 2) - $sin * $lift),
            $canvas->height / 2 - ($sin * ($width / 2) + $cos * $lift),
            $color, $opacity, 45,
        );
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array{banner: string, footer: string, color: array<int, float>, bannerOpacity: float, footerOpacity: float}
     */
    private function spec(array $in): array
    {
        $status = (string) $in['status'];
        $revision = "{$in['documentNumber']}  Terbitan {$in['major']} Revisi {$in['minor']}";
        $viewer = $in['viewerName'];
        $at = $in['accessedAt'];

        if ($status === 'effective' && ! empty($in['expired'])) {
            return ['banner' => 'UNCONTROLLED', 'footer' => "UNCONTROLLED / EXPIRED · {$revision} · Retrieved by {$viewer} on {$at}", 'color' => self::RED, 'bannerOpacity' => 0.14, 'footerOpacity' => 0.65];
        }

        return match ($status) {
            'effective' => ['banner' => 'CONTROLLED', 'footer' => "CONTROLLED COPY · {$revision} · Accessed by {$viewer} on {$at}", 'color' => [0.16, 0.32, 0.75], 'bannerOpacity' => 0.10, 'footerOpacity' => 0.55],
            'superseded' => ['banner' => 'UNCONTROLLED', 'footer' => "UNCONTROLLED · {$revision} · Retrieved by {$viewer} on {$at}", 'color' => self::RED, 'bannerOpacity' => 0.14, 'footerOpacity' => 0.65],
            default => (function () use ($status, $revision, $viewer, $at): array {
                $label = preg_replace('/_/', ' ', mb_strtoupper($status), 1);

                return ['banner' => $label, 'footer' => "{$label} · {$revision} · Accessed by {$viewer} on {$at}", 'color' => [0.45, 0.45, 0.45], 'bannerOpacity' => 0.12, 'footerOpacity' => 0.55];
            })(),
        };
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array{0: string, 1: array<int, float>}
     */
    private function headerStatus(array $in): array
    {
        if ($in['status'] === 'effective' && ! empty($in['expired'])) {
            return ['EXPIRED (UNCONTROLLED)', self::RED];
        }

        return match ($in['status']) {
            'effective' => ['CONTROLLED', [0.13, 0.50, 0.25]],
            'superseded' => ['UNCONTROLLED', self::RED],
            'approved' => ['DISETUJUI', [0.16, 0.32, 0.75]],
            'under_review' => ['DALAM REVIEW', [0.72, 0.45, 0.05]],
            default => ['DRAFT', [0.40, 0.42, 0.48]],
        };
    }
}
