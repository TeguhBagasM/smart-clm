<?php

namespace App\Models\Templates;

/**
 * A library row that can say how many other rows use it (the usage count of the list pages
 * and of the hide/show question).
 */
interface CountsUsage
{
    public function usageCount(): int;
}
