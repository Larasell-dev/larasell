<?php

namespace Larasell\Larasell\Licensing\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Exceptions\Licensing\LicenseException;
use Larasell\Larasell\Licensing\License;

/**
 * Scheduled daily, so the 14-day lease never runs out while the license is valid.
 */
class RenewLicenseCommand extends Command
{
    protected $signature = 'larasell:license:renew';

    protected $description = 'Renew the signed lease of this installation\'s Larasell license.';

    public function handle(License $license): int
    {
        try {
            $lease = $license->renew();
        } catch (LicenseException $exception) {
            $this->error("The license could not be renewed: {$exception->reason}.");

            return self::FAILURE;
        }

        $this->info("Renewed the {$lease->plan->name} plan until {$lease->expiresAt->toDateString()}.");

        return self::SUCCESS;
    }
}
