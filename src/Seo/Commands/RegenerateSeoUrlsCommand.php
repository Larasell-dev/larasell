<?php

namespace Larasell\Larasell\Seo\Commands;

use Illuminate\Console\Command;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;

class RegenerateSeoUrlsCommand extends Command
{
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
            fn (SalesContext $context): bool => (! is_string($channel) || $context->channel === $channel)
                && (! is_string($locale) || strcasecmp($context->locale->value, $locale) === 0),
        ));

        if ($types === [] || $contexts === []) {
            $this->error('No resource type or context matches.');

            return self::FAILURE;
        }

        $changes = [];
        $origin = Origin::cli(new SystemActor('larasell:regenerate-seo-urls'));
        $refreshAll = function () use ($types, $contexts, $manager, $origin, &$changes): void {
            foreach ($types as $type) {
                // Each page in its own transaction, so admins can keep saving; subcategories follow their parent.
                foreach ($type->model()::query()->lazyById() as $resource) {
                    array_push($changes, ...$manager->refresh($resource, $origin, $contexts));
                }
            }
        };

        if (! $this->option('dry-run')) {
            $refreshAll();
        } else {
            // One transaction that is rolled back, so nothing is saved; it holds every page's lock until then.
            $connection = (new SeoUrl)->getConnection();
            $connection->beginTransaction();

            try {
                $refreshAll();
            } finally {
                $connection->rollBack();
            }
        }

        if ($changes !== []) {
            $this->table(['Channel', 'Locale', 'Old path', 'New path'], array_map(fn (SeoUrlChanged $change): array => [
                $change->context->channel, $change->context->locale->value, $change->oldPath ?? '-', $change->newPath ?? '-',
            ], $changes));
        }

        $this->info(count($changes).' '.str('URL')->plural(count($changes)).($this->option('dry-run') ? ' would change.' : ' changed.'));

        return self::SUCCESS;
    }
}
