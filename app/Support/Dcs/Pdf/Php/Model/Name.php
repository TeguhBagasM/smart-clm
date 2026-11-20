<?php

namespace App\Support\Dcs\Pdf\Php\Model;

/** A PDF name object (/Type). */
final class Name
{
    public function __construct(public readonly string $value) {}
}
