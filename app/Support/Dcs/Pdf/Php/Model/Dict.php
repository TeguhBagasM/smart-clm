<?php

namespace App\Support\Dcs\Pdf\Php\Model;

/** A dictionary. */
final class Dict
{
    /** @param array<string, mixed> $items */
    public function __construct(public array $items = []) {}

    public function get(string $key): mixed
    {
        return $this->items[$key] ?? null;
    }
}
