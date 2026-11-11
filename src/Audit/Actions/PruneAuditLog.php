<?php

namespace Larasell\Larasell\Audit\Actions;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecords\AuditLogPruned as Pruned;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Events\AuditLogPruned;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Origin;

/**
 * Deletes the oldest audit records, as long as they're older than the retention period, and
 * records that it ran.
 *
 * Only ever the start of the chain: it stops at the first record still in the period, so an older
 * record behind it is kept until that one goes. A gap anywhere else is tampering.
 *
 * @public Used by larasell:prune-audit-log; listen to AuditLogPruned to clean up after it.
 */
class PruneAuditLog
{
    public function __construct(private readonly Auditor $auditor, private readonly AuditChain $chain) {}

    /**
     * @return int How many records were deleted.
     */
    public function handle(PruneAuditLogPayload $payload, Origin $origin): int
    {
        return DB::transaction(function () use ($payload, $origin): int {
            $deleted = 0;

            if ($payload->retention !== null && ! $payload->legalHold) {
                $cutoff = Date::now()->utc()->subDays($payload->retention->days)->format('Y-m-d H:i:s.u');
                $firstKept = DB::table(AuditChain::TABLE)->where('occurred_at', '>=', $cutoff)->min('seq');

                $doomed = DB::table(AuditChain::TABLE)->when($firstKept !== null, fn ($query) => $query->where('seq', '<', $firstKept));
                $from = (int) (clone $doomed)->min('seq');
                $until = (int) (clone $doomed)->max('seq');
                $stored = (clone $doomed)->count();

                // The oldest stretch is whole, or deleting it would hide what is already missing.
                if ($stored > 0 && $stored !== $until - $from + 1) {
                    throw AuditFailed::prunedOverGap($from, $until, $stored);
                }

                $deleted = $doomed->delete();

                // What is left starts right after what was deleted.
                if ($deleted !== $stored || $deleted > 0 && ($this->chain->startsAfter()['seq'] ?? null) !== $until) {
                    throw AuditFailed::prunedOverGap($from, $until, $deleted);
                }
            }

            $this->auditor->record(new Pruned($payload->legalHold, $payload->retention, $deleted, $this->chain->startsAfter()), $origin);

            AuditLogPruned::dispatch($deleted);

            return $deleted;
        });
    }
}
