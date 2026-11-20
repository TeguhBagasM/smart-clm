<?php

namespace App\Support\Dcs\Pdf\Php\Model;

use App\Support\Dcs\Pdf\PdfEngineException;
use Com\Tecnick\Pdf\Parser\Parser;
use Throwable;

/**
 * A PDF read into plain objects, edited page by page, and written back as a flat
 * (classic cross-reference table) file. Reading goes through tc-lib-pdf-parser,
 * which understands cross-reference and object streams, so a PDF written by pdf-lib,
 * Word or a browser all open; the objects of an object stream become ordinary
 * top-level objects on writing. Page content is never decoded or changed: an edit
 * only adds a stream after it.
 */
final class PdfFile
{
    /** @var array<int, array{0: int, 1: mixed}> number => [generation, value] */
    private array $objects = [];

    private int $nextNumber = 1;

    private int $names = 0;

    private function __construct(private Ref $root, private ?Ref $info, private string $version) {}

    /**
     * @throws PdfEngineException
     */
    public static function parse(string $bytes): self
    {
        if (! str_starts_with(ltrim(substr($bytes, 0, 1024)), '%PDF-')) {
            throw new PdfEngineException('The file is not a PDF.');
        }

        try {
            [$xref, $objects] = (new Parser(['decode_streams' => false]))->parse($bytes);
        } catch (Throwable $e) {
            throw new PdfEngineException('The PDF cannot be read: '.$e->getMessage(), previous: $e);
        }

        $trailer = $xref['trailer'] ?? [];

        if (isset($trailer['encrypt'])) {
            throw new PdfEngineException('The PDF is encrypted and cannot be edited.');
        }

        if (! isset($trailer['root'])) {
            throw new PdfEngineException('The PDF has no document catalog.');
        }

        preg_match('/%PDF-(\d\.\d)/', $bytes, $header);
        $file = new self(self::ref($trailer['root']), isset($trailer['info']) ? self::ref($trailer['info']) : null, $header[1] ?? '1.4');

        foreach ($objects as $key => $tokens) {
            [$number, $generation] = array_map('intval', explode('_', (string) $key));
            $value = self::object($tokens);

            if ($value instanceof PdfStream && in_array($value->dict->get('Type')?->value ?? null, ['ObjStm', 'XRef'], true)) {
                continue;
            }

            $file->objects[$number] = [$generation, $value];
            $file->nextNumber = max($file->nextNumber, $number + 1);
            $file->names = max($file->names, self::highestOwnName($value));
        }

        return $file;
    }

    /**
     * @return list<array{ref: Ref, dict: Dict, width: float, height: float}>
     */
    public function pages(): array
    {
        $pages = [];
        $this->collectPages($this->root(), [], $pages, 0, []);

        return $pages;
    }

    /** A resource name no earlier edit of this file used. */
    public function uniqueName(string $prefix): string
    {
        return $prefix.++$this->names;
    }

    public function add(mixed $value): Ref
    {
        $number = $this->nextNumber++;
        $this->objects[$number] = [0, $value];

        return new Ref($number);
    }

    /**
     * Appends drawing operators to a page, after everything already on it.
     *
     * @param  array<string, array<string, mixed>>  $resources  category (Font, XObject, ExtGState) => resource name => object
     */
    public function overlay(Ref $page, string $operators, array $resources): void
    {
        $leaf = $this->objects[$page->number][1];
        $current = $this->resolve($leaf->get('Resources'));
        $merged = new Dict($current instanceof Dict ? $current->items : []);

        foreach ($resources as $category => $added) {
            $existing = $this->resolve($merged->get($category));
            $merged->items[$category] = new Dict(($existing instanceof Dict ? $existing->items : []) + $added);
        }

        $contents = $this->resolve($leaf->get('Contents'));
        $existing = $leaf->get('Contents') instanceof Ref && $contents instanceof PdfStream ? [$leaf->get('Contents')] : (is_array($contents) ? $contents : []);

        $leaf->items['Resources'] = $merged;
        $leaf->items['Contents'] = [
            $this->add(new PdfStream(new Dict, "q\n")),
            ...$existing,
            $this->add(new PdfStream(new Dict, "\nQ\n".$operators)),
        ];
    }

    /** A new, empty document (catalog and page tree) to compose into. */
    public static function blank(string $producer): self
    {
        $file = new self(new Ref(1), new Ref(3), '1.7');
        $file->objects[1] = [0, new Dict(['Type' => new Name('Catalog'), 'Pages' => new Ref(2)])];
        $file->objects[2] = [0, new Dict(['Type' => new Name('Pages'), 'Kids' => [], 'Count' => 0])];
        $file->objects[3] = [0, new Dict(['Producer' => new PdfString($producer), 'Creator' => new PdfString($producer)])];
        $file->nextNumber = 4;

        return $file;
    }

    public function addPage(float $width, float $height): Ref
    {
        $pages = $this->objects[2][1];
        $page = $this->add(new Dict(['Type' => new Name('Page'), 'Parent' => new Ref(2), 'MediaBox' => [0, 0, $width, $height], 'Resources' => new Dict]));
        $pages->items['Kids'][] = $page;
        $pages->items['Count'] = count($pages->items['Kids']);

        return $page;
    }

    public function write(): string
    {
        $out = "%PDF-{$this->version}\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        ksort($this->objects);

        foreach ($this->objects as $number => [$generation, $value]) {
            $offsets[$number] = strlen($out);
            $out .= PdfSerializer::object($number, $generation, $value);
        }

        $size = max(array_keys($this->objects)) + 1;
        $xref = strlen($out);
        $out .= "xref\n0 {$size}\n0000000000 65535 f \n";

        for ($i = 1; $i < $size; $i++) {
            $out .= isset($offsets[$i]) ? sprintf("%010d %05d n \n", $offsets[$i], $this->objects[$i][0]) : "0000000000 65535 f \n";
        }

        $trailer = ['Size' => $size, 'Root' => $this->root] + ($this->info === null || ! isset($this->objects[$this->info->number]) ? [] : ['Info' => $this->info]);

        return $out."trailer\n".PdfSerializer::value(new Dict($trailer))."\nstartxref\n{$xref}\n%%EOF\n";
    }

    public function resolve(mixed $value, int $depth = 0): mixed
    {
        while ($value instanceof Ref && $depth++ < 32) {
            $value = $this->objects[$value->number][1] ?? null;
        }

        return $value;
    }

    private function root(): Dict
    {
        $catalog = $this->resolve($this->root);
        $pages = $catalog instanceof Dict ? $catalog->get('Pages') : null;

        return $this->resolve($pages) instanceof Dict ? $this->resolve($pages) : throw new PdfEngineException('The PDF has no page tree.');
    }

    /**
     * @param  array<string, mixed>  $inherited
     * @param  list<array{ref: Ref, dict: Dict, width: float, height: float}>  $pages
     * @param  array<int, true>  $seen
     */
    private function collectPages(Dict $node, array $inherited, array &$pages, int $depth, array $seen, ?Ref $self = null): void
    {
        foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $key) {
            if ($node->get($key) !== null) {
                $inherited[$key] = $node->get($key);
            }
        }

        $kids = $this->resolve($node->get('Kids'));

        if (is_array($kids) && $depth < 64) {
            foreach ($kids as $kid) {
                $child = $this->resolve($kid);

                if ($kid instanceof Ref && $child instanceof Dict && ! isset($seen[$kid->number])) {
                    $this->collectPages($child, $inherited, $pages, $depth + 1, $seen + [$kid->number => true], $kid);
                }
            }

            return;
        }

        if ($self === null) {
            return;
        }

        $box = $this->resolve($inherited['MediaBox'] ?? null);
        $box = is_array($box) ? array_map(fn (mixed $n): float => (float) $this->resolve($n), $box) : [0.0, 0.0, 595.28, 841.89];
        $node->items['Resources'] ??= $inherited['Resources'] ?? new Dict;
        $pages[] = ['ref' => $self, 'dict' => $node, 'width' => $box[2] - $box[0], 'height' => $box[3] - $box[1]];
    }

    /** The largest N among resource names "DcsXN" the value holds: names an earlier edit gave, which a new one must not reuse. */
    private static function highestOwnName(mixed $value): int
    {
        $highest = 0;

        foreach ($value instanceof PdfStream ? $value->dict->items : ($value instanceof Dict ? $value->items : []) as $key => $child) {
            if (preg_match('/^Dcs[A-Za-z]*?(\d+)$/', (string) $key, $m) === 1) {
                $highest = max($highest, (int) $m[1]);
            }

            $highest = max($highest, self::highestOwnName($child));
        }

        return $highest;
    }

    private static function ref(string $key): Ref
    {
        [$number, $generation] = array_map('intval', explode('_', $key));

        return new Ref($number, $generation);
    }

    /**
     * @param  list<array<int, mixed>>  $tokens
     */
    private static function object(array $tokens): mixed
    {
        $value = self::token($tokens[0]);
        $stream = null;

        foreach ($tokens as $token) {
            if ($token[0] === 'stream') {
                $stream = (string) $token[1];
            }
        }

        return $stream !== null && $value instanceof Dict ? new PdfStream($value, $stream) : $value;
    }

    /** @param array<int, mixed> $token */
    private static function token(array $token): mixed
    {
        return match ($token[0]) {
            'numeric' => str_contains((string) $token[1], '.') ? (float) $token[1] : (int) $token[1],
            'boolean' => (bool) $token[1],
            'null' => null,
            'objref' => self::ref($token[1]),
            '/' => new Name($token[1]),
            '(' => new PdfString((string) $token[1]),
            '<' => new PdfString((string) hex2bin(strlen((string) $token[1]) % 2 === 0 ? (string) $token[1] : $token[1].'0') ?: '', true),
            '[' => array_map(self::token(...), $token[1]),
            '<<' => self::dict($token[1]),
            default => throw new PdfEngineException("Unsupported PDF syntax ({$token[0]})."),
        };
    }

    /** @param list<array<int, mixed>> $tokens */
    private static function dict(array $tokens): Dict
    {
        $items = [];

        for ($i = 0; $i + 1 < count($tokens); $i += 2) {
            if ($tokens[$i][0] === '/') {
                $items[$tokens[$i][1]] = self::token($tokens[$i + 1]);
            }
        }

        return new Dict($items);
    }
}
