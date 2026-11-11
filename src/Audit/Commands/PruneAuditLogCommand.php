<?php

namespace Larasell\Larasell\Audit\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Audit\Actions\PruneAuditLog;
use Larasell\Larasell\Audit\Actions\PruneAuditLogPayload;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Exceptions\InvalidRetentionPeriod;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Audit\RetentionPeriod;

/**
 * Prunes the audit log with the retention period and legal hold set in config/larasell/audit.php.
 */
class PruneAuditLogCommand extends Command
{
    protected $signature = 'larasell:prune-audit-log';

    protected $description = 'Delete the oldest audit records once they are older than the retention period.';

    public function handle(PruneAuditLog $prune): int
    {
        try {
            $retention = RetentionPeriod::fromConfig(config('larasell.audit.retention.days'));
        } catch (InvalidRetentionPeriod $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = new PruneAuditLogPayload($retention, (bool) config('larasell.audit.legal_hold'));
        $deleted = $prune->handle($payload, Origin::cli(new SystemActor('larasell:prune-audit-log')));

        if ($payload->legalHold) {
            $this->components->warn('No audit records were deleted, since they are under a legal hold.');
        } elseif ($retention === null) {
            $this->components->info('No retention period is set in config/larasell/audit.php, so every audit record was kept.');
        } else {
            $this->components->info("{$deleted} audit records deleted.");
        }

        return self::SUCCESS;
    }
}
