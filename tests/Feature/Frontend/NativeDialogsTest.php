<?php

/**
 * Confirmations use the app's own dialog (`confirm()` from composables/useConfirm),
 * never the browser's confirm, alert or prompt. The only browser prompt left is
 * `beforeunload`, which a page cannot replace.
 */
it('has no native confirm, alert or prompt in the frontend or the Blade views', function () {
    $pattern = '/(?<![\w.$])(?:window\.)?(?:confirm|alert|prompt)\s*\(|\bon(?:click|submit)\s*=\s*["\']?[^"\'>]*confirm/i';
    $offenders = [];

    foreach ([resource_path('js'), resource_path('views')] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = $file->getPathname();

            if (! in_array($file->getExtension(), ['vue', 'ts', 'js', 'php'], true) || str_contains($path, '/js/actions/') || str_contains($path, '/js/routes/') || str_ends_with($path, 'composables/useConfirm.ts')) {
                continue;
            }

            foreach (file($path) as $number => $line) {
                $trimmed = ltrim($line);

                if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                if (preg_match($pattern, $line, $match) && ! str_contains($line, 'await confirm(') && ! str_contains($line, 'void confirm(') && ! str_contains($line, 'import ')) {
                    $offenders[] = str_replace(base_path().'/', '', $path).':'.($number + 1).' '.trim($match[0]);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
