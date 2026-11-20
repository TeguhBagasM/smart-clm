<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\Dict;
use App\Support\Dcs\Pdf\Php\Model\Name;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use App\Support\Dcs\Pdf\Php\Model\Ref;

/** One font object per base font in a file, shared by every page that draws with it. */
final class FontPool
{
    /** @var array<string, array{name: string, ref: Ref}> */
    private array $fonts = [];

    public function __construct(private PdfFile $file) {}

    public function name(StandardFont $font): string
    {
        return $this->entry($font)['name'];
    }

    public function ref(StandardFont $font): Ref
    {
        return $this->entry($font)['ref'];
    }

    /** @return array{name: string, ref: Ref} */
    private function entry(StandardFont $font): array
    {
        return $this->fonts[$font->baseFont] ??= [
            'name' => $this->file->uniqueName('DcsF'),
            'ref' => $this->file->add(new Dict([
                'Type' => new Name('Font'),
                'Subtype' => new Name('Type1'),
                'BaseFont' => new Name($font->baseFont),
                'Encoding' => new Name('WinAnsiEncoding'),
            ])),
        ];
    }
}
