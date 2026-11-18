<?php

use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Support\Dcs\NumberAllocator;
use Illuminate\Support\Facades\DB;

function allocatorRule(): NumberingRule
{
    return NumberingRule::factory()->for(Tenant::factory()->create())->create();
}

$tokens = ['DocType' => 'SOP', 'Department' => 'HRD', 'Year' => 2026];

it('refuses to run outside a transaction', function () use ($tokens) {
    $rule = allocatorRule();

    DB::rollBack();
    try {
        app(NumberAllocator::class)->allocate($rule, $tokens);
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class, 'inside a database transaction');

it('hands out consecutive numbers per scope', function () use ($tokens) {
    $rule = allocatorRule();
    $allocator = app(NumberAllocator::class);

    $numbers = DB::transaction(fn () => [
        $allocator->allocate($rule, $tokens)->seq,
        $allocator->allocate($rule, $tokens)->seq,
        $allocator->allocate($rule, [...$tokens, 'Department' => 'FIN'])->seq,
        $allocator->allocate($rule, $tokens)->seq,
    ]);

    expect($numbers)->toBe([1, 2, 1, 3]);
    expect(DB::table('dcs_number_sequences')->orderBy('scope_hash')->pluck('current_seq', 'scope_hash')->all())->toBe(['SOP|FIN|2026' => 1, 'SOP|HRD|2026' => 3]);
});

it('stores the scope values with the counter', function () use ($tokens) {
    $rule = allocatorRule();

    DB::transaction(fn () => app(NumberAllocator::class)->allocate($rule, [...$tokens, 'Month' => 7]));

    expect(json_decode(DB::table('dcs_number_sequences')->value('scope_values'), true))->toEqual($tokens);
});

it('peeks the next number without reserving it', function () use ($tokens) {
    $rule = allocatorRule();
    $allocator = app(NumberAllocator::class);

    expect($allocator->peek($rule, $tokens))->toBe(1);
    DB::transaction(fn () => $allocator->allocate($rule, $tokens));

    expect($allocator->peek($rule, $tokens))->toBe(2)
        ->and(DB::table('dcs_number_allocations')->count())->toBe(1);
});

it('gives a released number out again before moving the counter on', function () use ($tokens) {
    $rule = allocatorRule();
    $allocator = app(NumberAllocator::class);

    $seqs = DB::transaction(function () use ($allocator, $rule, $tokens) {
        $one = $allocator->allocate($rule, $tokens);
        $allocator->allocate($rule, $tokens);
        $allocator->release($one);

        return [$allocator->allocate($rule, $tokens)->seq, $allocator->allocate($rule, $tokens)->seq];
    });

    expect($seqs)->toBe([1, 3]);
});

it('returns the number when the surrounding transaction rolls back', function () use ($tokens) {
    $rule = allocatorRule();
    $allocator = app(NumberAllocator::class);

    try {
        DB::transaction(function () use ($allocator, $rule, $tokens) {
            $allocator->allocate($rule, $tokens);
            throw new RuntimeException('document failed');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => $allocator->allocate($rule, $tokens)->seq))->toBe(1);
});
