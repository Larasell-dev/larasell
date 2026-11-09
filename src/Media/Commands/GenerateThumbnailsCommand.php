<?php

namespace Larasell\Larasell\Media\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;

/** Queues thumbnails for images without one, e.g. uploaded while no worker ran, or all of them. */
final class GenerateThumbnailsCommand extends Command
{
    protected $signature = 'larasell:media:thumbnails {--force : Remake every thumbnail, e.g. after larasell.media.thumbnails.size changed}';

    protected $description = 'Queue thumbnails for the images and videos in the media library that have none';

    public function handle(): int
    {
        $query = Media::query();

        if (! $this->option('force')) {
            $query->whereDoesntHave('assets', fn ($assets) => $assets->where('role', MediaAsset::THUMBNAIL));
        }

        $count = 0;
        // In chunks, so a large library never sits in memory at once.
        $query->select('id')->chunkById(500, function ($media) use (&$count): void {
            foreach ($media as $item) {
                GenerateThumbnail::dispatch(MediaId::of($item));
                $count++;
            }
        });

        $this->info("Queued {$count} thumbnail(s).");

        return self::SUCCESS;
    }
}
