<?php

namespace App\Support\Dcs\Pdf\Php\Model;

/** A stream: its dictionary and the bytes exactly as stored (still encoded by /Filter). */
final class PdfStream
{
    public function __construct(public Dict $dict, public readonly string $data) {}
}
