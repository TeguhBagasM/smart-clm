<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\FontPool;
use App\Support\Dcs\Pdf\Php\Draw\ImageEmbedder;
use App\Support\Dcs\Pdf\Php\Draw\PageCanvas;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;

/** The document being composed: its file, its fonts and the pages added so far. */
final class Page
{
    /** @var list<PageCanvas> */
    public array $pages = [];

    public readonly FontPool $pool;

    public readonly ImageEmbedder $images;

    public readonly StandardFont $regular;

    public readonly StandardFont $bold;

    public readonly StandardFont $bodyRegular;

    public readonly StandardFont $bodyBold;

    public function __construct(public readonly PdfFile $file, public readonly Layout $layout, public readonly bool $formal)
    {
        $this->pool = new FontPool($file);
        $this->images = new ImageEmbedder;
        $this->regular = StandardFont::named('Helvetica');
        $this->bold = StandardFont::named('Helvetica-Bold');
        $this->bodyRegular = $formal ? StandardFont::named('Times-Roman') : $this->regular;
        $this->bodyBold = $formal ? StandardFont::named('Times-Bold') : $this->bold;
    }

    public function addPage(): PageCanvas
    {
        $ref = $this->file->addPage(Layout::PAGE_W, Layout::PAGE_H);

        return $this->pages[] = new PageCanvas($this->file, $this->pool, $ref, Layout::PAGE_W, Layout::PAGE_H);
    }

    /** Starts a new page unless `$needed` points still fit under the cursor. */
    public function ensureSpace(Cursor $cursor, float $needed): void
    {
        if ($cursor->y - $needed < $this->layout->mBottom) {
            $cursor->page = $this->addPage();
            $cursor->y = $this->layout->contentTopY;
        }
    }
}
