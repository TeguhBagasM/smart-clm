<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\PageCanvas;

/** Where the next line of body text goes: a page and the y of what is already used. */
final class Cursor
{
    public function __construct(public PageCanvas $page, public float $y) {}
}
