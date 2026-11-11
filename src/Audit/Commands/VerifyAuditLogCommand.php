<?php

namespace Larasell\Larasell\Audit\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Audit\Chain\AuditChain;

/**
 * Checks that no audit record was changed, deleted or slipped in outside the app.
 */
class VerifyAuditLogCommand extends Command
{
    protected $signature = 'larasell:verify-audit-log';

    protected $description = 'Check that no audit record was changed or deleted outside the app.';

    public function handle(AuditChain $chain): int
    {
        $verification = $chain->verify();

        if (! $verification->passed) {
            $this->components->error("The audit log's chain is broken at record {$verification->seq}: {$verification->reason}. {$verification->verified} records before it verified.");

            return self::FAILURE;
        }

        $this->components->info("{$verification->verified} audit records verified.".($verification->hash === null ? '' : " The chain ends at record {$verification->seq}, {$verification->hash}."));

        return self::SUCCESS;
    }
}
