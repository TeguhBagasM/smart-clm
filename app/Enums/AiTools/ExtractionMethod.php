<?php

namespace App\Enums\AiTools;

enum ExtractionMethod: string
{
    case TextLayer = 'text_layer';
    case Ocr = 'ocr';
    case Mixed = 'mixed';
    case Structured = 'structured';
    case Pasted = 'pasted';
}
