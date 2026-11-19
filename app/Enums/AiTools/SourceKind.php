<?php

namespace App\Enums\AiTools;

enum SourceKind: string
{
    case Upload = 'upload';
    case DcsVersion = 'dcs_version';
    case Paste = 'paste';
}
