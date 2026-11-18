<?php

namespace App\Support\Dcs;

use App\Models\Dcs\NumberAllocation;
use App\Models\Dcs\NumberingRule;
use App\Models\Dcs\NumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out document numbers. Concurrent creators of the same scope (rule +
 * scope hash) are serialized by a row lock on the scope's counter, so a number
 * is never given twice; different scopes never wait for each other. A number
 * released earlier is given out before the counter moves on, so cancelled
 * documents leave no gap. Allocation and release must run inside the
 * transaction that creates or changes the document: the lock lives until it
 * commits and a rollback returns the number.
 */
class NumberAllocator
{
    /**
     * The tokens that depend on the clock, read in the numbering time zone.
     *
     * @return array{Year: int, Month: int, Day: int}
     */
    public function clockTokens(): array
    {
        $now = now(config('dcs.numbering_timezone') ?? config('app.timezone'));

        return ['Year' => $now->year, 'Month' => $now->month, 'Day' => $now->day];
    }

    /**
     * @param  array<string, string|int>  $tokens
     */
    public function allocate(NumberingRule $rule, array $tokens): NumberAllocation
    {
        $this->requireTransaction();

        $sequence = $this->lockedSequence($rule, $tokens);

        $pooled = NumberAllocation::query()
            ->where('sequence_id', $sequence->id)
            ->whereNotNull('released_at')
            ->orderBy('seq')
            ->first();

        if ($pooled !== null) {
            $pooled->forceFill(['released_at' => null])->save();

            return $pooled;
        }

        $sequence->forceFill(['current_seq' => $sequence->current_seq + 1])->save();

        return NumberAllocation::query()->forceCreate(['sequence_id' => $sequence->id, 'seq' => $sequence->current_seq]);
    }

    /**
     * The sequence number the next document would get, without reserving it.
     * It ignores the pool of released numbers, as the Node preview did.
     *
     * @param  array<string, string|int>  $tokens
     */
    public function peek(NumberingRule $rule, array $tokens): int
    {
        $current = NumberSequence::query()
            ->where('rule_id', $rule->id)
            ->where('scope_hash', NumberingMask::scopeHash($rule->scope_tokens, $tokens))
            ->value('current_seq');

        return ((int) $current) + 1;
    }

    /**
     * Gives a number back. The last number handed out is simply taken back off
     * the counter; any other waits in the pool.
     */
    public function release(NumberAllocation $allocation): void
    {
        $this->requireTransaction();

        $sequence = NumberSequence::query()->whereKey($allocation->sequence_id)->lockForUpdate()->firstOrFail();

        if ($allocation->seq === $sequence->current_seq) {
            $sequence->forceFill(['current_seq' => $sequence->current_seq - 1])->save();
            $allocation->delete();

            return;
        }

        $allocation->forceFill(['document_id' => null, 'released_at' => now()])->save();
    }

    /**
     * @param  array<string, string|int>  $tokens
     */
    private function lockedSequence(NumberingRule $rule, array $tokens): NumberSequence
    {
        $hash = NumberingMask::scopeHash($rule->scope_tokens, $tokens);
        $now = now();

        DB::table('dcs_number_sequences')->insertOrIgnore([
            'tenant_id' => $rule->tenant_id,
            'rule_id' => $rule->id,
            'scope_hash' => $hash,
            'scope_values' => json_encode(array_intersect_key($tokens, array_flip($rule->scope_tokens))),
            'current_seq' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return NumberSequence::query()
            ->where('rule_id', $rule->id)
            ->where('scope_hash', $hash)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be allocated or released inside a database transaction.');
        }
    }
}
