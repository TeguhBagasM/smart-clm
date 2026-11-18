<?php

namespace App\Enums\Dcs;

enum ReviewCommentStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
