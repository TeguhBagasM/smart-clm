<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\Php\Model\Ref;

/** An image already placed in the file, ready to be drawn by name on any page. */
final class EmbeddedImage
{
    public function __construct(public readonly string $name, public readonly Ref $ref, public readonly int $width, public readonly int $height) {}
}
