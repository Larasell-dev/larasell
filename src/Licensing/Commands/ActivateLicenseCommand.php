<?php

namespace Larasell\Larasell\Licensing\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Exceptions\Licensing\LicenseException;
use Larasell\Larasell\Licensing\License;

class ActivateLicenseCommand extends Command
{
    protected $signature = 'larasell:license:activate {key : The license key from your larasell.dev dashboard}';

    protected $description = 'Activate this installation with a Larasell license key.';

    public function handle(License $license): int
    {
        $key = $this->argument('key');

        if (! is_string($key)) {
            return self::INVALID;
        }

        try {
            $lease = $license->activate($key);
        } catch (LicenseException $exception) {
            $this->error("The license could not be activated: {$exception->reason}.");

            return self::FAILURE;
        }

        $this->info("Activated the {$lease->plan->name} plan for {$lease->domain}.");

        return self::SUCCESS;
    }
}
