<?php

namespace App\Support\Dcs\Pdf\Php\Model;

/** An indirect reference (12 0 R). */
final class Ref
{
    public function __construct(public readonly int $number, public readonly int $generation = 0) {}
}
