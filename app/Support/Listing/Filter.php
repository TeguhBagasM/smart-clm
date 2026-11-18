<?php

namespace App\Support\Listing;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * One filter of a list. The kind decides how the request value is read and
 * checked (a select only accepts its own options, a date range only real
 * dates) and how the frontend draws it; `$apply` receives the cleaned value.
 * Anything that does not pass the check is ignored, never an error.
 */
final class Filter
{
    public const SELECT = 'select';

    public const MULTI = 'multi';

    public const BOOLEAN = 'boolean';

    public const DATE_RANGE = 'date_range';

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly array $options,
        private readonly Closure $apply,
        public readonly bool $primary,
    ) {}

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @param  Closure(Builder<*>, string): mixed  $apply
     */
    public static function select(string $key, string $label, array $options, Closure $apply, bool $primary = false): self
    {
        return new self($key, $label, self::SELECT, $options, $apply, $primary);
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @param  Closure(Builder<*>, array<int, string>): mixed  $apply
     */
    public static function multi(string $key, string $label, array $options, Closure $apply, bool $primary = false): self
    {
        return new self($key, $label, self::MULTI, $options, $apply, $primary);
    }

    /**
     * @param  Closure(Builder<*>, bool): mixed  $apply
     */
    public static function boolean(string $key, string $label, Closure $apply, bool $primary = false): self
    {
        return new self($key, $label, self::BOOLEAN, [], $apply, $primary);
    }

    /**
     * @param  Closure(Builder<*>, string|null, string|null): mixed  $apply  receives the start and end day (Y-m-d), either may be null
     */
    public static function dateRange(string $key, string $label, Closure $apply, bool $primary = false): self
    {
        return new self($key, $label, self::DATE_RANGE, [], $apply, $primary);
    }

    /**
     * The value as the filter understands it, or null when the request value is empty or invalid.
     */
    public function parse(mixed $raw): mixed
    {
        return match ($this->type) {
            self::SELECT => is_string($raw) && $this->hasOption($raw) ? $raw : null,
            self::MULTI => $this->parseMulti($raw),
            self::BOOLEAN => match ($raw) {
                '1', 'true', 1, true => true,
                '0', 'false', 0, false => false,
                default => null,
            },
            default => $this->parseRange($raw),
        };
    }

    /**
     * @param  Builder<*>  $query
     */
    public function apply(Builder $query, mixed $value): void
    {
        $value = $this->type === self::DATE_RANGE ? [$value['from'], $value['to']] : [$value];
        ($this->apply)($query, ...$value);
    }

    /**
     * What the frontend needs to draw the filter and its current value.
     *
     * @return array<string, mixed>
     */
    public function schema(mixed $value): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'type' => $this->type, 'options' => $this->options, 'primary' => $this->primary, 'value' => $value];
    }

    private function hasOption(string $value): bool
    {
        return in_array($value, array_column($this->options, 'value'), true);
    }

    /**
     * @return array<int, string>|null
     */
    private function parseMulti(mixed $raw): ?array
    {
        $values = array_values(array_filter(is_array($raw) ? $raw : [$raw], fn (mixed $item): bool => is_string($item) && $this->hasOption($item)));

        return $values === [] ? null : array_values(array_unique($values));
    }

    /**
     * @return array{from: string|null, to: string|null}|null
     */
    private function parseRange(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $range = ['from' => $this->parseDay($raw['from'] ?? null), 'to' => $this->parseDay($raw['to'] ?? null)];

        return $range['from'] === null && $range['to'] === null ? null : $range;
    }

    private function parseDay(mixed $raw): ?string
    {
        if (! is_string($raw) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $raw)->format('Y-m-d') === $raw ? $raw : null;
        } catch (Throwable) {
            return null;
        }
    }
}
