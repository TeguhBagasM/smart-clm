<?php

namespace App\Support\Comparison;

use App\Enums\AiTools\Pairing;

/**
 * Pairs the articles of document A with those of document B without a model: the same number with
 * similar text, or very similar text under a different number (renumbered laws). What is left over
 * is for the model to try (see AlignArticles); articles still unmatched become added/removed rows.
 */
class ArticleMatcher
{
    private const SIMILAR = 0.45;

    private const SAME_LABEL = 0.2;

    private const LABEL_BONUS = 0.15;

    /**
     * @param  list<array{ordinal: int, number_label: string|null, heading: string|null, body: string}>  $a
     * @param  list<array{ordinal: int, number_label: string|null, heading: string|null, body: string}>  $b
     * @return array{pairs: list<array{a: int, b: int, pairing: Pairing}>, unmatched_a: list<int>, unmatched_b: list<int>}
     */
    public function match(array $a, array $b): array
    {
        $tokensA = $this->tokenSets($a);
        $tokensB = $this->tokenSets($b);

        $candidates = [];

        foreach ($a as $i => $left) {
            foreach ($b as $j => $right) {
                $sameLabel = $this->label($left) === $this->label($right);
                $similarity = $this->jaccard($tokensA[$i], $tokensB[$j]);

                if ($sameLabel && ($left['number_label'] !== null && str_starts_with($this->label($left), 'bab'))) {
                    $similarity = max($similarity, 0.3);
                }

                if (($sameLabel && $similarity >= self::SAME_LABEL) || $similarity >= self::SIMILAR) {
                    $candidates[] = [$similarity + ($sameLabel ? self::LABEL_BONUS : 0), $left['ordinal'], $right['ordinal'], $sameLabel];
                }
            }
        }

        usort($candidates, fn (array $x, array $y): int => [$y[0], $x[1], $x[2]] <=> [$x[0], $y[1], $y[2]]);

        $usedA = $usedB = $pairs = [];

        foreach ($candidates as [, $left, $right, $sameLabel]) {
            if (isset($usedA[$left]) || isset($usedB[$right])) {
                continue;
            }

            $usedA[$left] = $usedB[$right] = true;
            $pairs[] = ['a' => $left, 'b' => $right, 'pairing' => $sameLabel ? Pairing::Number : Pairing::Similar];
        }

        return [
            'pairs' => $pairs,
            'unmatched_a' => array_values(array_filter(array_column($a, 'ordinal'), fn (int $o): bool => ! isset($usedA[$o]))),
            'unmatched_b' => array_values(array_filter(array_column($b, 'ordinal'), fn (int $o): bool => ! isset($usedB[$o]))),
        ];
    }

    /**
     * Orders the matrix like document A, putting an article that exists only in B right after the
     * row that holds the B article before it.
     *
     * @param  list<array{a: int, b: int, pairing: Pairing}>  $pairs
     * @param  list<int>  $allA
     * @param  list<int>  $allB
     * @return list<array{a: int|null, b: int|null, pairing: Pairing}>
     */
    public function arrange(array $pairs, array $allA, array $allB): array
    {
        $pairByA = [];
        $anchorByB = [];

        foreach ($pairs as $pair) {
            $pairByA[$pair['a']] = $pair;
            $anchorByB[$pair['b']] = $pair['a'];
        }

        $rows = [];

        foreach ($allA as $ordinal) {
            $rows[] = [(float) $ordinal, ['a' => $ordinal, 'b' => $pairByA[$ordinal]['b'] ?? null, 'pairing' => $pairByA[$ordinal]['pairing'] ?? Pairing::None]];
        }

        $matchedB = array_column($pairs, 'b');
        sort($matchedB);

        foreach (array_diff($allB, $matchedB) as $ordinal) {
            $before = array_filter($matchedB, fn (int $b): bool => $b < $ordinal);
            $anchor = $before === [] ? 0 : $anchorByB[max($before)];
            $rows[] = [$anchor + 0.5 + $ordinal / 1_000_000, ['a' => null, 'b' => $ordinal, 'pairing' => Pairing::None]];
        }

        usort($rows, fn (array $x, array $y): int => $x[0] <=> $y[0]);

        return array_column($rows, 1);
    }

    /**
     * @param  array{number_label: string|null, heading: string|null}  $article
     */
    private function label(array $article): string
    {
        return $article['number_label'] === null ? 'pembukaan' : mb_strtolower(preg_replace('/\s+/u', '', $article['number_label']) ?? '');
    }

    /**
     * @param  list<array{heading: string|null, body: string}>  $articles
     * @return list<array<string, true>>
     */
    private function tokenSets(array $articles): array
    {
        return array_map(function (array $article): array {
            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(($article['heading'] ?? '').' '.$article['body']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_fill_keys($words, true);
        }, $articles);
    }

    /**
     * @param  array<string, true>  $x
     * @param  array<string, true>  $y
     */
    private function jaccard(array $x, array $y): float
    {
        if ($x === [] || $y === []) {
            return 0.0;
        }

        $shared = count(array_intersect_key($x, $y));

        return $shared / (count($x) + count($y) - $shared);
    }
}
