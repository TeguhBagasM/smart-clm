<?php

namespace App\Support\Comparison;

/**
 * Cuts a document into articles ("Pasal 1", "BAB II", a preamble) with plain pattern matching, so the
 * model never sees a whole document and every row of the matrix can be checked against its source.
 *
 * Strategy, in order: `Pasal n` lines; numbered headings ("1. TUJUAN", for documents written in the
 * system); fixed-size chunks when neither exists (the caller shows a warning).
 */
class ArticleSplitter
{
    public const STRATEGY_PASAL = 'pasal';

    public const STRATEGY_NUMBERED = 'numbered';

    public const STRATEGY_CHUNKS = 'chunks';

    private const PASAL = '/^\s*(Pasal\s+\d+[A-Z]?)\s*(?:[:.\-–—]\s*(\S.{0,200}))?$/iu';

    private const BAB = '/^\s*(BAB\s+[IVXLC\d]+[A-Z]?)\s*(?:[:.\-–—]\s*(\S.{0,200}))?$/u';

    private const NUMBERED = '/^\s*(\d{1,2})[.)]\s+(\p{Lu}[^\n]{2,120})$/u';

    private const LIST_MARKER = '/^(\(?\d+[.)]|\(\d+\)|\(?[a-z][.)]|[-•–])\s/u';

    /**
     * @return array{strategy: string, articles: list<array{number_label: string|null, heading: string|null, body: string}>}
     */
    public function split(string $text): array
    {
        $lines = $this->lines($text);

        foreach ([self::STRATEGY_PASAL => self::PASAL, self::STRATEGY_NUMBERED => self::NUMBERED] as $strategy => $pattern) {
            $articles = $this->cut($lines, $pattern, $strategy);

            if ($this->headingCount($articles) >= 2) {
                return ['strategy' => $strategy, 'articles' => $articles];
            }
        }

        return ['strategy' => self::STRATEGY_CHUNKS, 'articles' => $this->chunks($lines)];
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            $line = trim($line);

            if (preg_match('/^(Halaman|Page)\s+\d+(\s+(dari|of)\s+\d+)?$/iu', $line) || preg_match('/^[-–]\s*\d+\s*[-–]$/u', $line)) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{number_label: string|null, heading: string|null, body: string}>
     */
    private function cut(array $lines, string $pattern, string $strategy): array
    {
        $articles = [];
        $current = ['number_label' => null, 'heading' => 'Pembukaan', 'lines' => []];

        foreach ($lines as $line) {
            $boundary = $this->boundary($line, $pattern, $strategy);

            if ($boundary === null) {
                $current['lines'][] = $line;

                continue;
            }

            $articles[] = $current;
            $current = ['number_label' => $boundary[0], 'heading' => $boundary[1], 'lines' => []];
        }

        $articles[] = $current;

        $result = [];

        foreach ($articles as $article) {
            $body = $this->paragraphs($article['lines']);

            if ($article['number_label'] === null && $body === '') {
                continue;
            }

            $result[] = ['number_label' => $article['number_label'], 'heading' => $article['heading'], 'body' => $body];
        }

        return $result;
    }

    /**
     * @return array{string, string|null}|null
     */
    private function boundary(string $line, string $pattern, string $strategy): ?array
    {
        if ($strategy === self::STRATEGY_PASAL && preg_match(self::BAB, $line, $m)) {
            return [preg_replace('/\s+/u', ' ', mb_strtoupper($m[1])) ?? $m[1], $m[2] ?? null];
        }

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        if ($strategy === self::STRATEGY_NUMBERED) {
            return [$m[1].'.', trim($m[2])];
        }

        return [ucfirst(mb_strtolower(preg_replace('/\s+/u', ' ', $m[1]) ?? $m[1])), isset($m[2]) ? trim($m[2]) : null];
    }

    /**
     * @param  list<array{number_label: string|null, heading: string|null, body: string}>  $articles
     */
    private function headingCount(array $articles): int
    {
        return count(array_filter($articles, fn (array $article): bool => $article['number_label'] !== null));
    }

    /**
     * Joins lines hard-wrapped by the PDF into paragraphs; list items and sentences that ended keep their break.
     *
     * @param  list<string>  $lines
     */
    private function paragraphs(array $lines): string
    {
        $paragraphs = [];
        $current = '';

        foreach ($lines as $line) {
            if ($line === '') {
                if ($current !== '') {
                    $paragraphs[] = $current;
                    $current = '';
                }

                continue;
            }

            if ($current === '') {
                $current = $line;
            } elseif (preg_match(self::LIST_MARKER, $line) || preg_match('/[.;:]$/u', $current) && preg_match('/^\p{Lu}/u', $line)) {
                $paragraphs[] = $current;
                $current = $line;
            } else {
                $current .= (str_ends_with($current, '-') ? '' : ' ').$line;
            }
        }

        if ($current !== '') {
            $paragraphs[] = $current;
        }

        return implode("\n", $paragraphs);
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{number_label: string|null, heading: string|null, body: string}>
     */
    private function chunks(array $lines): array
    {
        $paragraphs = array_values(array_filter(explode("\n", $this->paragraphs($lines)), fn (string $p): bool => $p !== ''));
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($paragraph) > 1500) {
                $chunks[] = $buffer;
                $buffer = '';
            }

            $buffer .= ($buffer === '' ? '' : "\n").$paragraph;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_map(fn (string $body, int $i): array => ['number_label' => null, 'heading' => 'Bagian '.($i + 1), 'body' => $body], $chunks, array_keys($chunks));
    }
}
