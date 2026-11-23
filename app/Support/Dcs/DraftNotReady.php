<?php

namespace App\Support\Dcs;

/**
 * The content of a draft has problems that would stop its PDF from being drawn (or drawn
 * wrongly). Each problem says where it is and how to fix it, so the author can act on it.
 */
class DraftNotReady extends DcsException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(422, 'Dokumen belum bisa disusun. Perbaiki '.count($problems).' hal berikut: '.implode(' ', $problems));
    }
}
