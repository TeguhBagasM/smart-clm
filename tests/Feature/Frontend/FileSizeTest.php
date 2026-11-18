<?php

use Symfony\Component\Finder\Finder;

/**
 * Frontend files stay small enough to read at a glance: at most 200 lines, so
 * a page or component that grows must be split into reusable parts. Generated
 * code (Wayfinder) and the shadcn-vue primitives are not ours to split.
 */
const MAX_FRONTEND_LINES = 200;

/**
 * Starter-kit files that were already longer when the limit was introduced.
 * They are only listed so the test can pass today: split them when touched and
 * remove them here. Nothing new may be added to this list.
 */
const OVER_LIMIT_ALLOWED = ['components/AppHeader.vue', 'components/TwoFactorSetupModal.vue'];

it('keeps every handwritten Vue and TypeScript file within the line limit', function () {
    $files = Finder::create()->files()->in(resource_path('js'))->name(['*.vue', '*.ts'])
        ->notPath(['components/ui', 'actions', 'routes', 'wayfinder']);

    $tooLong = [];

    foreach ($files as $file) {
        $lines = substr_count(rtrim((string) file_get_contents($file->getRealPath())), "\n") + 1;

        if ($lines > MAX_FRONTEND_LINES && ! in_array($file->getRelativePathname(), OVER_LIMIT_ALLOWED, true)) {
            $tooLong[] = "{$file->getRelativePathname()} ({$lines} lines)";
        }
    }

    expect($tooLong)->toBe([], 'Split these files into smaller components or composables: '.implode(', ', $tooLong));
});
