<?php

namespace Larasell\Larasell\Images\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\ProductImage;

class RefreshPlaceholdersCommand extends Command
{
    protected $signature = 'larasell:refresh-placeholders
        {--batch-size=100 : The number of images to load at a time}';

    protected $description = 'Regenerate product image placeholders with the configured generator.';

    public function handle(ModelRegistry $models): int
    {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($batchSize === false) {
            $this->error('The batch size must be a positive integer.');

            return self::FAILURE;
        }

        $refreshed = 0;

        $models->productImage->query()
            ->chunkById($batchSize, function (Collection $images) use (&$refreshed): void {
                /** @var ProductImage $image */
                foreach ($images as $image) {
                    $image->refreshPlaceholder();
                    $image->save();
                    $refreshed++;
                }
            });

        $noun = $refreshed === 1 ? 'image' : 'images';
        $this->info("Refreshed placeholders for {$refreshed} {$noun}.");

        return self::SUCCESS;
    }
}
