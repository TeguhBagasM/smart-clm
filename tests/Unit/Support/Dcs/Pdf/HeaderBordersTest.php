<?php

use App\Support\Dcs\Pdf\Php\Compose\HeaderBorders;

/*
 * Which sides of a letterhead box are drawn: a side shared with a neighbour needs both to want it.
 * The editor preview has the same rule (tests/js/header/borders.test.ts).
 */

$all = ['top' => true, 'right' => true, 'bottom' => true, 'left' => true];
$none = ['top' => false, 'right' => false, 'bottom' => false, 'left' => false];

it('draws every side when every box wants every side', function () use ($all) {
    $boxes = [['x' => 0, 'y' => 0, 'w' => 50, 'h' => 20, 'want' => $all], ['x' => 50, 'y' => 0, 'w' => 50, 'h' => 20, 'want' => $all]];

    expect(HeaderBorders::resolve($boxes))->toBe([$all, $all]);
});

it('hides the shared side when either box hides it, so a box with no lines shows none', function () use ($all, $none) {
    $boxes = [['x' => 0, 'y' => 0, 'w' => 50, 'h' => 20, 'want' => $none], ['x' => 50, 'y' => 0, 'w' => 50, 'h' => 20, 'want' => $all]];

    expect(HeaderBorders::resolve($boxes))->toBe([$none, [...$all, 'left' => false]]);
});

it('keeps the outer sides of a box that has no neighbour there and hides the shared one below', function () use ($all, $none) {
    $boxes = [
        ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 20, 'want' => $all],
        ['x' => 0, 'y' => 20, 'w' => 100, 'h' => 12, 'want' => [...$none, 'bottom' => true]],
    ];

    expect(HeaderBorders::resolve($boxes)[0])->toBe([...$all, 'bottom' => false])
        ->and(HeaderBorders::resolve($boxes)[1])->toBe([...$none, 'bottom' => true]);
});

it('looks at the length two boxes share, not only at the box next to them', function () use ($all, $none) {
    $logo = ['x' => 0, 'y' => 0, 'w' => 20, 'h' => 40, 'want' => $none];
    $second = ['x' => 20, 'y' => 20, 'w' => 80, 'h' => 20, 'want' => $all];

    expect(HeaderBorders::resolve([$logo, $second])[1]['left'])->toBeFalse()
        ->and(HeaderBorders::resolve([['x' => 0, 'y' => 0, 'w' => 20, 'h' => 20, 'want' => $none], $second])[1]['left'])->toBeTrue();
});
