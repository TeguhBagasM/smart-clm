<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ChangeKind;
use App\Enums\AiTools\Pairing;
use App\Models\AiTools\ComparisonArticle;
use App\Models\AiTools\DocumentComparison;
use App\Support\Ai\InvalidAiOutput;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\StructuredGenerator;
use App\Support\Comparison\ArticleMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pairs the articles of A and B and creates the rows of the matrix. The code pairs what it can (same
 * number, similar text); the model only gets what is left, to catch provisions that moved or were
 * renumbered, and may answer nothing but pairs of ids it was given.
 */
class AlignComparisonArticles
{
    private const CHUNK = 40;

    private const MAX_CANDIDATES = 150;

    public function __construct(private readonly ArticleMatcher $matcher, private readonly StructuredGenerator $generator) {}

    public function handle(DocumentComparison $comparison): void
    {
        $articles = $comparison->articles()->orderBy('side')->orderBy('ordinal')->get()->groupBy('side');
        $a = $articles->get('a', collect());
        $b = $articles->get('b', collect());

        $result = $this->matcher->match($this->plain($a), $this->plain($b));
        $pairs = $result['pairs'];

        if ($result['unmatched_a'] !== [] && $result['unmatched_b'] !== []) {
            $pairs = [...$pairs, ...$this->askModel($comparison, $a, $b, $result['unmatched_a'], $result['unmatched_b'])];
        }

        $rows = $this->matcher->arrange($pairs, $this->ordinals($a), $this->ordinals($b));
        $idA = $a->pluck('id', 'ordinal');
        $idB = $b->pluck('id', 'ordinal');

        DB::transaction(function () use ($comparison, $rows, $idA, $idB): void {
            $comparison->rows()->delete();

            foreach ($rows as $index => $row) {
                $comparison->rows()->create([
                    'ordinal' => $index + 1,
                    'article_a_id' => $row['a'] === null ? null : $idA[$row['a']],
                    'article_b_id' => $row['b'] === null ? null : $idB[$row['b']],
                    'pairing' => $row['pairing'],
                    'change_kind' => $row['a'] === null ? ChangeKind::Added : ($row['b'] === null ? ChangeKind::Removed : ChangeKind::Changed),
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, ComparisonArticle>  $articles
     * @return list<int>
     */
    private function ordinals(Collection $articles): array
    {
        return array_values(array_map(fn (ComparisonArticle $article): int => $article->ordinal, $articles->all()));
    }

    /**
     * @param  Collection<int, ComparisonArticle>  $articles
     * @return list<array{ordinal: int, number_label: string|null, heading: string|null, body: string}>
     */
    private function plain(Collection $articles): array
    {
        return array_values($articles->map(fn (ComparisonArticle $article): array => $article->only(['ordinal', 'number_label', 'heading', 'body']))->all());
    }

    /**
     * @param  Collection<int, ComparisonArticle>  $a
     * @param  Collection<int, ComparisonArticle>  $b
     * @param  list<int>  $leftA
     * @param  list<int>  $leftB
     * @return list<array{a: int, b: int, pairing: Pairing}>
     */
    private function askModel(DocumentComparison $comparison, Collection $a, Collection $b, array $leftA, array $leftB): array
    {
        $pairs = [];
        $remainingB = array_slice($leftB, 0, self::MAX_CANDIDATES);

        try {
            foreach (array_chunk($leftA, self::CHUNK) as $chunk) {
                if ($remainingB === []) {
                    break;
                }

                $found = $this->align($comparison, $a->whereIn('ordinal', $chunk), $b->whereIn('ordinal', $remainingB));

                foreach ($found as $pair) {
                    $pairs[] = [...$pair, 'pairing' => Pairing::Llm];
                    $remainingB = array_values(array_diff($remainingB, [$pair['b']]));
                }
            }
        } catch (InvalidAiOutput|LlmException) {
            $comparison->warn('AI tidak berhasil memasangkan pasal yang berubah nomor; pasal tersebut ditampilkan sebagai dihapus dan baru.');
        }

        return $pairs;
    }

    /**
     * @param  Collection<int, ComparisonArticle>  $a
     * @param  Collection<int, ComparisonArticle>  $b
     * @return list<array{a: int, b: int}>
     */
    private function align(DocumentComparison $comparison, Collection $a, Collection $b): array
    {
        $describe = fn (Collection $set): string => (string) json_encode($set->map(fn (ComparisonArticle $article): array => [
            'id' => $article->ref(),
            'nomor' => $article->number_label,
            'judul' => $article->heading,
            'isi' => Str::limit($article->body, 220, '…'),
        ])->values()->all(), JSON_UNESCAPED_UNICODE);

        $prompt = "Berikut pasal-pasal yang belum berpasangan.\n\nDOKUMEN A:\n{$describe($a)}\n\nDOKUMEN B:\n{$describe($b)}\n\n"
            .'Pasangkan pasal A dengan pasal B hanya bila keduanya mengatur hal yang sama (misalnya karena dipindah atau berganti nomor). '
            .'Jangan memaksakan: pasal tanpa padanan jangan dimasukkan. Satu id hanya boleh dipakai sekali. '
            .'Balas HANYA JSON: {"pairs":[{"a":"A3","b":"B7"}]} dan {"pairs":[]} bila tidak ada.';

        $idsA = $a->map(fn (ComparisonArticle $x): string => $x->ref())->all();
        $idsB = $b->map(fn (ComparisonArticle $x): string => $x->ref())->all();

        $reply = $this->generator->generate('align', new LlmRequest($prompt, 'Anda pakar hukum Indonesia yang memadankan pasal antar dokumen hukum.'), [
            'pairs' => ['present', 'array'],
            'pairs.*.a' => ['required', 'string'],
            'pairs.*.b' => ['required', 'string'],
        ], $comparison, function (array $data) use ($idsA, $idsB): array {
            $problems = [];
            $pairs = $data['pairs'];

            foreach ($pairs as $pair) {
                if (! in_array($pair['a'], $idsA, true) || ! in_array($pair['b'], $idsB, true)) {
                    $problems[] = "Pasangan {$pair['a']}-{$pair['b']} memakai id yang tidak ada.";
                }
            }

            if (count(array_unique(array_column($pairs, 'a'))) !== count($pairs) || count(array_unique(array_column($pairs, 'b'))) !== count($pairs)) {
                $problems[] = 'Satu id dipakai lebih dari sekali.';
            }

            return $problems;
        });

        return array_values(array_map(fn (array $pair): array => ['a' => (int) substr($pair['a'], 1), 'b' => (int) substr($pair['b'], 1)], $reply['pairs']));
    }
}
