<?php

namespace App\Support\Comparison;

/**
 * Word-level diff computed in code (never by a model): what the matrix paints red/struck (removed
 * from A) and green (added in B). Whitespace is ignored, case and punctuation are not.
 */
class WordDiff
{
    /** Above this many cells the middle part is shown as one removal and one addition. */
    private const MAX_CELLS = 6_000_000;

    /**
     * `o` is "eq", "del" (A side only) or "ins" (B side only).
     *
     * @return array{a: list<array{t: string, o: string}>, b: list<array{t: string, o: string}>}
     */
    public function diff(string $a, string $b): array
    {
        $x = $this->words($a);
        $y = $this->words($b);

        $start = 0;
        $endX = count($x);
        $endY = count($y);

        while ($start < $endX && $start < $endY && $x[$start] === $y[$start]) {
            $start++;
        }

        while ($endX > $start && $endY > $start && $x[$endX - 1] === $y[$endY - 1]) {
            $endX--;
            $endY--;
        }

        $midX = array_slice($x, $start, $endX - $start);
        $midY = array_slice($y, $start, $endY - $start);

        $sideA = $sideB = [];

        foreach (array_slice($x, 0, $start) as $word) {
            $sideA[] = [$word, 'eq'];
            $sideB[] = [$word, 'eq'];
        }

        foreach ($this->middle($midX, $midY) as [$word, $op]) {
            if ($op !== 'ins') {
                $sideA[] = [$word, $op];
            }

            if ($op !== 'del') {
                $sideB[] = [$word, $op];
            }
        }

        foreach (array_slice($x, $endX) as $word) {
            $sideA[] = [$word, 'eq'];
            $sideB[] = [$word, 'eq'];
        }

        return ['a' => $this->runs($sideA), 'b' => $this->runs($sideB)];
    }

    /**
     * Same text once whitespace is ignored.
     */
    public function same(string $a, string $b): bool
    {
        return $this->words($a) === $this->words($b);
    }

    /**
     * @return list<string>
     */
    public function words(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  list<string>  $x
     * @param  list<string>  $y
     * @return list<array{string, string}>
     */
    private function middle(array $x, array $y): array
    {
        $n = count($x);
        $m = count($y);

        if ($n === 0 || $m === 0 || $n * $m > self::MAX_CELLS) {
            return [...array_map(fn (string $w): array => [$w, 'del'], $x), ...array_map(fn (string $w): array => [$w, 'ins'], $y)];
        }

        $table = [];
        $table[$n] = array_fill(0, $m + 1, 0);

        for ($i = $n - 1; $i >= 0; $i--) {
            $row = array_fill(0, $m + 1, 0);

            for ($j = $m - 1; $j >= 0; $j--) {
                $row[$j] = $x[$i] === $y[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $row[$j + 1]);
            }

            $table[$i] = $row;
        }

        $ops = [];
        $i = $j = 0;

        while ($i < $n && $j < $m) {
            if ($x[$i] === $y[$j]) {
                $ops[] = [$x[$i++], 'eq'];
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = [$x[$i++], 'del'];
            } else {
                $ops[] = [$y[$j++], 'ins'];
            }
        }

        while ($i < $n) {
            $ops[] = [$x[$i++], 'del'];
        }

        while ($j < $m) {
            $ops[] = [$y[$j++], 'ins'];
        }

        return $ops;
    }

    /**
     * @param  list<array{string, string}>  $words
     * @return list<array{t: string, o: string}>
     */
    private function runs(array $words): array
    {
        $runs = [];

        foreach ($words as [$word, $op]) {
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last]['o'] === $op) {
                $runs[$last]['t'] .= ' '.$word;
            } else {
                $runs[] = ['t' => $word, 'o' => $op];
            }
        }

        return $runs;
    }
}
