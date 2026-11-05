<?php

namespace Larasell\Larasell\Seo;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Larasell\Larasell\Models\ModelEntry;
use Larasell\Larasell\Seo\Contracts\CollisionPolicy;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Events\SeoResourceGone;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Events\SeoUrlReleased;
use Larasell\Larasell\Seo\Jobs\RefreshSeoUrls;
use Larasell\Larasell\Seo\Models\SeoUrl;
use LogicException;

/**
 * The only code that writes SEO URLs. Rules it keeps:
 *
 * - a resource has at most one canonical row per channel and locale;
 * - a path that was public is only freed by remove() or by another resource claiming it;
 * - while a resource was never available, path changes overwrite its row instead of leaving history;
 * - automatic updates never touch a custom row.
 */
final class SeoUrlManager
{
    private const MAX_ATTEMPTS = 100;

    /** @var ModelEntry<SeoUrl> */
    private readonly ModelEntry $urls;

    public function __construct(
        private readonly SeoResources $resources,
        private readonly SeoContextResolver $contextResolver,
        private readonly CollisionPolicy $collisions,
        private readonly PathNormalizer $paths,
        Repository $config,
    ) {
        $this->urls = new ModelEntry($config, 'larasell.seo.models.seo_url', SeoUrl::class);
    }

    /**
     * One context per channel and locale. When several entry points serve the same pair, the first one wins.
     *
     * @return list<SeoContext>
     */
    public function contexts(): array
    {
        $contexts = [];

        foreach ($this->contextResolver->contexts() as $context) {
            $contexts[$context->channel.'|'.$context->locale->value] ??= $context;
        }

        return array_values($contexts);
    }

    public function canonical(Model $resource, SeoContext $context): ?SeoUrl
    {
        $rows = $this->rows($this->resources->for($resource)->key(), $resource->getKey(), $context)
            ->where('status', SeoUrlStatus::Canonical->value)
            ->limit(2)
            ->get();

        // The unique index on is_canonical makes a second row impossible; reading one means the index is missing.
        if ($rows->count() > 1) {
            throw new LogicException('['.$resource::class.' '.$resource->getKey()."] has more than one canonical URL in [{$context->channel}/{$context->locale->value}].");
        }

        return $rows->first();
    }

    /** @param  string  $type  The resource type's key(), e.g. "category" */
    public function canonicalPath(string $type, int|string $id, SeoContext $context): ?string
    {
        $path = $this->rows($type, $id, $context)->where('status', SeoUrlStatus::Canonical->value)->value('path');

        return $path === null ? null : (string) $path;
    }

    /**
     * Brings the resource's automatic URLs up to date in every context at once, or in none when one
     * fails, and queues its dependents when a path changed.
     *
     * @param  list<SeoContext>|null  $contexts  null for every context
     * @return list<SeoUrlChanged> One change per context whose path changed
     */
    public function refresh(Model $resource, ?array $contexts = null, bool $cascade = true): array
    {
        $type = $this->resources->for($resource);

        $changes = $this->transaction(function () use ($type, $resource, $contexts): array {
            $changes = [];

            foreach ($contexts ?? $this->contexts() as $context) {
                if ($change = $this->refreshIn($type, $resource, $context)) {
                    $changes[] = $change;
                }
            }

            return $changes;
        });

        if ($changes !== [] && $cascade) {
            $this->refreshDependents($type, $resource);
        }

        return $changes;
    }

    /** Makes the path the resource's custom URL. Renames never change it until resetToAutomatic(). */
    public function setCustom(Model $resource, SeoContext $context, string $path): SeoUrl
    {
        $type = $this->resources->for($resource);
        $path = $this->normalize($path);

        [$row, $changed] = $this->transaction(function () use ($type, $resource, $context, $path): array {
            $current = $this->canonical($resource, $context);
            $row = $this->place($type, $resource, $context, $path, true, $current);

            return [$row, $this->changed($resource, $context, $current?->path, $row->path) !== null];
        });

        if ($changed) {
            $this->refreshDependents($type, $resource);
        }

        return $row;
    }

    public function resetToAutomatic(Model $resource, SeoContext $context): void
    {
        $type = $this->resources->for($resource);

        $changed = $this->transaction(function () use ($type, $resource, $context): bool {
            $current = $this->canonical($resource, $context);

            if ($current === null || ! $current->is_custom) {
                return false;
            }

            $current->update(['is_custom' => false]);

            return $this->refreshIn($type, $resource, $context) !== null;
        });

        if ($changed) {
            $this->refreshDependents($type, $resource);
        }
    }

    /**
     * Keeps the deleted resource's public URLs as tombstones, with the snapshot its type takes. URLs
     * that were never public are dropped. A replacement makes the tombstones lead to it; it can be
     * given before or after the resource is deleted.
     */
    public function markGone(Model $resource, ?Model $replacement = null): void
    {
        $type = $this->resources->for($resource);
        $replacementKey = $replacement === null ? null : $this->resources->for($replacement)->key();

        if ($replacementKey === $type->key() && $replacement->getKey() == $resource->getKey()) {
            throw new LogicException('A page cannot be its own replacement.');
        }

        $gone = $this->transaction(function () use ($type, $resource, $replacement, $replacementKey): bool {
            $gone = false;

            foreach ($this->contexts() as $context) {
                $rows = $this->rows($type->key(), $resource->getKey(), $context);
                $live = (clone $rows)->where('status', '!=', SeoUrlStatus::Gone->value);

                (clone $live)->whereNull('went_public_at')->delete();

                $gone = $live->update([
                    'status' => SeoUrlStatus::Gone->value,
                    'gone_snapshot' => json_encode($type->snapshot($resource, $context), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]) > 0 || $gone;

                if ($replacement !== null) {
                    $rows->update(['replacement_type' => $replacementKey, 'replacement_id' => $replacement->getKey(), 'updated_at' => now()]);
                }
            }

            return $gone;
        });

        if ($gone) {
            event(new SeoResourceGone($resource));
        }
    }

    /** Frees an old or gone URL, so the path is unknown again or can be used by another page. */
    public function remove(SeoUrl $url): void
    {
        if ($url->status === SeoUrlStatus::Canonical) {
            throw new LogicException('A canonical URL cannot be removed. Give the page another URL first.');
        }

        $url->delete();
        event(new SeoUrlReleased($url));
    }

    /** @param  SeoResourceType<Model>  $type */
    private function refreshIn(SeoResourceType $type, Model $resource, SeoContext $context): ?SeoUrlChanged
    {
        $current = $this->canonical($resource, $context);
        $available = $type->isAvailable($resource, $context);

        if ($current?->is_custom) {
            $this->markPublic($current, $available);

            return null;
        }

        $path = $type->path($resource, $context);

        if ($path === null) {
            // No page in this context any more, e.g. its translation was removed.
            if ($current !== null) {
                $this->retire($current);
            }

            return $this->changed($resource, $context, $current?->path, null);
        }

        $row = $this->place($type, $resource, $context, $path, false, $current, $available);

        return $this->changed($resource, $context, $current?->path, $row->path);
    }

    /**
     * Makes the path canonical for the resource, reusing its old row for that path if it has one.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function place(SeoResourceType $type, Model $resource, SeoContext $context, string $path, bool $custom, ?SeoUrl $current, ?bool $available = null): SeoUrl
    {
        $path = $this->freePath($type, $resource, $context, $path, $custom);
        $available ??= $type->isAvailable($resource, $context);

        if ($current?->path === $path) {
            $row = $current->fill(['is_custom' => $custom]);
        } elseif ($own = $this->rows($type->key(), $resource->getKey(), $context)->where('path', $path)->first()) {
            // Renamed back: the old URL becomes canonical again instead of a duplicate being created.
            if ($current !== null) {
                $this->retire($current);
            }

            $row = $own->fill(['status' => SeoUrlStatus::Canonical, 'is_custom' => $custom]);
        } else {
            // Retiring drops a row that was never public (draft rule), so drafts leave no history.
            if ($current !== null) {
                $this->retire($current);
            }

            $row = $this->urls->new()->fill([
                'channel' => $context->channel,
                'locale' => $context->locale->value,
                'path' => $path,
                'resource_type' => $type->key(),
                'resource_id' => $resource->getKey(),
                'status' => SeoUrlStatus::Canonical,
                'is_custom' => $custom,
                'went_public_at' => $current?->went_public_at,
            ]);
        }

        $this->markPublic($row, $available);

        return $row;
    }

    /**
     * The first candidate that is not reserved for another type or used by another page's canonical URL.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function freePath(SeoResourceType $type, Model $resource, SeoContext $context, string $path, bool $custom): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $attempt === 0 ? $path : $this->collisions->alternative($path, $attempt, $custom);

            if ($candidate === null) {
                throw InvalidSeoPath::taken($path);
            }

            if ($this->resources->isReservedFor($candidate, $type, $context)) {
                if ($custom) {
                    throw InvalidSeoPath::reserved($candidate);
                }

                continue;
            }

            $row = $this->urls->query()
                ->where('channel', $context->channel)
                ->where('locale', $context->locale->value)
                ->where('path', $candidate)
                ->first();

            if ($row === null || ($row->resource_type === $type->key() && $row->resource_id === (int) $resource->getKey())) {
                return $candidate;
            }

            if ($row->status !== SeoUrlStatus::Canonical) {
                // Another page's old or gone URL: the new canonical URL claims it.
                $row->delete();
                event(new SeoUrlReleased($row));

                return $candidate;
            }
        }

        throw InvalidSeoPath::taken($path);
    }

    private function retire(SeoUrl $row): void
    {
        $row->went_public_at === null
            ? $row->delete()
            : $row->update(['status' => SeoUrlStatus::Historic, 'is_custom' => false]);
    }

    private function markPublic(SeoUrl $row, bool $available): void
    {
        if ($available && $row->went_public_at === null) {
            $row->went_public_at = now();
        }

        $row->save();
    }

    /** Announces the change once the transaction commits, and returns it; null when nothing changed. */
    private function changed(Model $resource, SeoContext $context, ?string $old, ?string $new): ?SeoUrlChanged
    {
        if ($old === $new) {
            return null;
        }

        event($change = new SeoUrlChanged($resource, $context, $old, $new));

        return $change;
    }

    /** @param  SeoResourceType<Model>  $type */
    private function refreshDependents(SeoResourceType $type, Model $resource): void
    {
        $dependents = $type->dependents($resource);

        if ($dependents->isNotEmpty()) {
            RefreshSeoUrls::dispatch($dependents);
        }
    }

    /** @return Builder<SeoUrl> */
    private function rows(string $type, int|string $id, SeoContext $context): Builder
    {
        return $this->urls->query()
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->where('channel', $context->channel)
            ->where('locale', $context->locale->value);
    }

    /**
     * Runs the writes in a transaction. The unique index settles a race with another request for the same
     * path, so a unique violation is retried once against the now visible winner.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function transaction(Closure $callback): mixed
    {
        $connection = $this->urls->new()->getConnection();

        try {
            return $connection->transaction($callback);
        } catch (UniqueConstraintViolationException) {
            return $connection->transaction($callback);
        }
    }

    private function normalize(string $path): string
    {
        $path = $this->paths->normalize(trim($path));
        $segment = '[\p{L}\p{N}][\p{L}\p{N}._-]*';

        if (mb_strlen($path) > SeoUrl::MAX_PATH_LENGTH || preg_match("~^{$segment}(?:/{$segment})*$~u", $path) !== 1) {
            throw InvalidSeoPath::malformed($path);
        }

        return $path;
    }
}
