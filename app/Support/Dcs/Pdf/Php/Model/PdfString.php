<?php

namespace App\Support\Dcs\Pdf\Php\Model;

/** A string object, literal or hexadecimal, kept as raw bytes. */
final class PdfString
{
    public function __construct(public readonly string $bytes, public readonly bool $hex = false) {}
}
