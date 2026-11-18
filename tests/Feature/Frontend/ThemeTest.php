<?php

/**
 * Reads the colour tokens of one theme block from resources/css/app.css.
 *
 * @return array<string, string> token name (without leading dashes) => hex colour
 */
function themeTokens(string $selector): array
{
    $css = file_get_contents(base_path('resources/css/app.css'));

    preg_match('/^'.preg_quote($selector, '/').' \{(.*?)^\}/ms', $css, $block);
    preg_match_all('/--([a-z-]+):\s*(#[0-9a-f]{6});/i', $block[1] ?? '', $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

function relativeLuminance(string $hex): float
{
    return array_sum(array_map(
        function (int $channel, float $weight): float {
            $c = $channel / 255;

            return $weight * ($c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
        },
        array_map('hexdec', str_split(ltrim($hex, '#'), 2)),
        [0.2126, 0.7152, 0.0722],
    ));
}

function contrastRatio(string $a, string $b): float
{
    [$high, $low] = [max(relativeLuminance($a), relativeLuminance($b)), min(relativeLuminance($a), relativeLuminance($b))];

    return ($high + 0.05) / ($low + 0.05);
}

/**
 * Text pairs need 4.5:1; control boundaries and focus rings need 3:1 (WCAG AA).
 *
 * @return array<string, array{string, string, string, float}>
 */
function themeContrastPairs(): array
{
    $textPairs = [
        ['foreground', 'background'], ['card-foreground', 'card'], ['popover-foreground', 'popover'],
        ['foreground', 'muted'], ['muted-foreground', 'background'], ['muted-foreground', 'card'], ['muted-foreground', 'muted'],
        ['primary-foreground', 'primary'], ['primary-foreground', 'primary-hover'],
        ['primary', 'background'], ['primary', 'card'], ['primary', 'muted'], ['primary', 'accent'],
        ['accent-foreground', 'accent'], ['secondary-foreground', 'secondary'],
        ['highlight-foreground', 'highlight'], ['destructive-foreground', 'destructive'],
        ['destructive', 'background'], ['destructive', 'card'],
        ['success-foreground', 'success'], ['success', 'background'], ['warning-foreground', 'warning'],
        ['sidebar-foreground', 'sidebar'], ['sidebar-primary-foreground', 'sidebar-primary'],
        ['sidebar-accent-foreground', 'sidebar-accent'], ['primary', 'sidebar'], ['muted-foreground', 'sidebar'],
    ];
    $controlPairs = [['input', 'background'], ['input', 'card'], ['ring', 'background'], ['primary', 'background']];

    $pairs = [];

    foreach (['light' => ':root', 'dark' => '.dark'] as $mode => $selector) {
        foreach ($textPairs as [$foreground, $background]) {
            $pairs["{$mode}: {$foreground} on {$background}"] = [$selector, $foreground, $background, 4.5];
        }

        foreach ($controlPairs as [$foreground, $background]) {
            $pairs["{$mode}: {$foreground} against {$background}"] = [$selector, $foreground, $background, 3.0];
        }
    }

    return $pairs;
}

it('keeps theme colour pairs at WCAG AA contrast', function (string $selector, string $foreground, string $background, float $minimum) {
    $tokens = themeTokens($selector);

    expect($tokens)->toHaveKeys([$foreground, $background])
        ->and(contrastRatio($tokens[$foreground], $tokens[$background]))->toBeGreaterThanOrEqual($minimum);
})->with(themeContrastPairs());

it('defines the same tokens in the light and the dark theme', function () {
    expect(array_keys(themeTokens('.dark')))->toEqualCanonicalizing(array_keys(themeTokens(':root')));
});

it('uses the same background for the boot style in the layout as the theme', function () {
    $layout = file_get_contents(resource_path('views/app.blade.php'));

    preg_match('/html \{\s*background-color: (#[0-9a-f]{6});/i', $layout, $light);
    preg_match('/html\.dark \{\s*background-color: (#[0-9a-f]{6});/i', $layout, $dark);

    expect(strtolower($light[1] ?? ''))->toBe(themeTokens(':root')['background'])
        ->and(strtolower($dark[1] ?? ''))->toBe(themeTokens('.dark')['background']);
});

it('keeps literal colours out of the frontend components', function () {
    $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black';
    $literal = '/(?<![\w-])(?:bg|text|border|ring|fill|stroke|decoration|outline|divide|from|via|to|shadow|accent|caret)-(?:'.$palette.')(?:-\d{2,3})?(?![\w-])'
        .'|\[#|#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|oklch\(|rgba?\(|hsla?\(/i';

    $offenders = [];

    foreach (['app.ts', 'components', 'layouts', 'pages', 'composables', 'lib'] as $path) {
        $target = resource_path('js/'.$path);
        $files = is_dir($target)
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS))
            : [new SplFileInfo($target)];

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match($literal, $line, $match)) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1).' '.trim($match[0]);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
