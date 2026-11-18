<?php

namespace App\Enums\Dcs;

/**
 * How a document type lays out its PDF: the three curated styles of the Node
 * application (SOP/IK/STD, internal memo, policy).
 */
enum LayoutStyle: string
{
    case Standard = 'standard';
    case Memo = 'memo';
    case Kebijakan = 'kebijakan';

    public function defaultHeaderStyle(): HeaderStyle
    {
        return $this === self::Memo ? HeaderStyle::Simple : HeaderStyle::Iso;
    }
}
