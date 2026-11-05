<?php

namespace Larasell\Larasell\Seo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;

class RegenerateSeoUrlsCommand extends Command
{
    /** Passes over all resources until no path changes; deep category trees settle one level per pass. */
    private const MAX_PASSES = 20;

    protected $signature = 'larasell:regenerate-seo-urls
        {type? : Only this resource type, by model name, e.g. "product"}
        {--channel= : Only this channel}
        {--locale= : Only this locale}
        {--dry-run : List the changes without saving them}';

    protected $description = 'Recompute automatic SEO URLs, e.g. after changing a URL strategy or bulk updates that skipped model events.';

    public function handle(SeoUrlManager $manager, SeoResources $resources): int
    {
        $name = $this->argument('type');
        $channel = $this->option('channel');
        $locale = $this->option('locale');

        $types = array_values(array_filter(
            $resources->all(),
            fn (SeoResourceType $type): bool => ! is_string($name) || strcasecmp(class_basename($type->model()), $name) === 0,
        ));
        $contexts = array_values(array_filter(
            $manager->contexts(),
            fn (SeoContext $context): bool => (! is_string($channel) || $context->channel === $channel)
                && (! is_string($locale) || strcasecmp($context->locale->value, $locale) === 0),
        ));

        if ($types === [] || $contexts === []) {
            $this->error('No resource type or context matches.');

            return self::FAILURE;
        }

        $changes = [];
        Event::listen(SeoUrlChanged::class, function (SeoUrlChanged $event) use (&$changes): void {
            $changes[] = [$event->context->channel, $event->context->locale->value, $event->oldPath ?? '-', $event->newPath ?? '-'];
        });

        $connection = (new SeoUrl)->getConnection();
        $connection->beginTransaction();

        try {
            for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
                $changed = 0;

                foreach ($types as $type) {
                    foreach ($type->model()::query()->lazyById() as $resource) {
                        $changed += $manager->refresh($resource, $contexts, cascade: false);
                    }
                }

                if ($changed === 0) {
                    break;
                }
            }
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->option('dry-run') ? $connection->rollBack() : $connection->commit();

        if ($changes !== []) {
            $this->table(['Channel', 'Locale', 'Old path', 'New path'], $changes);
        }

        $this->info(count($changes).' '.str('URL')->plural(count($changes)).($this->option('dry-run') ? ' would change.' : ' changed.'));

        return self::SUCCESS;
    }
}
