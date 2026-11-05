<?php

namespace Larasell\Larasell\Seo;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Contracts\CollisionPolicy;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Events\SeoResourceGone;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Events\SeoUrlReleased;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\Storefronts;
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
    public function __construct(
        private readonly SeoResources $resources,
        private readonly Storefronts $storefronts,
        private readonly CollisionPolicy $collisions,
        private readonly PathNormalizer $paths,
    ) {}

    /**
     * One context per storefront channel and locale; each has its own URLs.
     *
     * @return list<SalesContext>
     */
    public function contexts(): array
    {
        return array_map(fn (StorefrontContext $context): SalesContext => $context->sales, $this->storefronts->contexts());
    }

    public function canonical(Model $resource, SalesContext $context): ?SeoUrl
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
    public function canonicalPath(string $type, int|string $id, SalesContext $context): ?string
    {
        $path = $this->rows($type, $id, $context)->where('status', SeoUrlStatus::Canonical->value)->value('path');

        return $path === null ? null : (string) $path;
    }

    /**
     * Brings the resource's automatic URLs up to date in every context at once, or in none when one
     * fails, together with the pages built on its URL, such as subcategories.
     *
     * @param  list<SalesContext>|null  $contexts  null for every context
     * @return list<SeoUrlChanged> One change per page and context whose path changed
     */
    public function refresh(Model $resource, ?array $contexts = null, bool $cascade = true): array
    {
        return $this->refreshPage($this->resources->for($resource), $resource->getKey(), $contexts, $cascade);
    }

    /**
     * Like refresh(), by ID: the page is loaded and locked inside the transaction, so a stale copy
     * cannot write outdated URLs, and a page that no longer exists gets none.
     *
     * @param  SeoResourceType<Model>  $type
     * @param  list<SalesContext>|null  $contexts  null for every context
     * @return list<SeoUrlChanged>
     */
    public function refreshPage(SeoResourceType $type, int|string $id, ?array $contexts = null, bool $cascade = true): array
    {
        return $this->transaction(fn (): array => $this->refreshTree($type, $id, $contexts ?? $this->contexts(), $cascade, []));
    }

    /** Makes the path the resource's custom URL. Renames never change it until resetToAutomatic(). */
    public function setCustom(Model $resource, SalesContext $context, string $path): SeoUrl
    {
        $type = $this->resources->for($resource);
        $path = $this->normalize($path);

        return $this->transaction(function () use ($type, $resource, $context, $path): SeoUrl {
            $resource = $this->lock($type, $resource->getKey()) ?? throw (new ModelNotFoundException)->setModel($resource::class, [$resource->getKey()]);
            $current = $this->canonical($resource, $context);
            $row = $this->place($type, $resource, $context, $path, true, $current);

            if ($this->changed($type, $resource, $context, $current?->path, $row->path) !== null) {
                $this->refreshDependents($type, $resource, [$context], []);
            }

            return $row;
        });
    }

    public function resetToAutomatic(Model $resource, SalesContext $context): void
    {
        $type = $this->resources->for($resource);

        $this->transaction(function () use ($type, $resource, $context): void {
            $resource = $this->lock($type, $resource->getKey());
            $current = $resource === null ? null : $this->canonical($resource, $context);

            if ($resource === null || $current === null || ! $current->is_custom) {
                return;
            }

            $current->update(['is_custom' => false]);

            if ($this->refreshIn($type, $resource, $context) !== null) {
                $this->refreshDependents($type, $resource, [$context], []);
            }
        });
    }

    /**
     * Keeps the deleted resource's public URLs as tombstones, with the snapshot its type takes. URLs
     * that were never public are dropped. A replacement makes the tombstones lead to it; it can be
     * given before or after the resource is deleted.
     */
    public function markGone(Model $resource, ?Model $replacement = null): void
    {
        $type = $this->resources->for($resource);

        $this->gone($type, $resource->getKey(), fn (SalesContext $context): array => $type->snapshot($resource, $context), $replacement);
    }

    /**
     * Like markGone(), by ID, for a page that is already deleted, with what was known about it.
     *
     * @param  SeoResourceType<Model>  $type
     * @param  Closure(SalesContext): array<string, mixed>  $snapshot
     */
    public function markGoneById(SeoResourceType $type, int|string $id, Closure $snapshot): void
    {
        $this->gone($type, $id, $snapshot, null);
    }

    /**
     * Leads the URLs of a deleted page to its replacement, e.g. after DeleteProduct. Use markGone()
     * to delete and replace in one go when the page is still in memory.
     *
     * @param  SeoResourceType<Model>  $type
     */
    public function setReplacement(SeoResourceType $type, int|string $id, Model $replacement): void
    {
        $this->gone($type, $id, fn (): never => throw new LogicException("[{$type->key()} {$id}] still has live URLs: mark it gone first."), $replacement);
    }

    /** Frees an old or gone URL, so the path is unknown again or can be used by another page. */
    public function remove(SeoUrl $url): void
    {
        if ($url->status === SeoUrlStatus::Canonical) {
            throw new LogicException('A canonical URL cannot be removed. Give the page another URL first.');
        }

        $url->delete();
        event(SeoUrlReleased::of($url));
    }

    /** @param  SeoResourceType<Model>  $type */
    private function refreshIn(SeoResourceType $type, Model $resource, SalesContext $context): ?SeoUrlChanged
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

            return $this->changed($type, $resource, $context, $current?->path, null);
        }

        $row = $this->place($type, $resource, $context, $path, false, $current, $available);

        return $this->changed($type, $resource, $context, $current?->path, $row->path);
    }

    /**
     * Makes the path canonical for the resource, reusing its old row for that path if it has one.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function place(SeoResourceType $type, Model $resource, SalesContext $context, string $path, bool $custom, ?SeoUrl $current, ?bool $available = null): SeoUrl
    {
        $path = $this->freePath($type, $resource, $context, $path, $custom);
        $available ??= $type->isAvailable($resource, $context);

        if ($current?->path === $path) {
            $row = $current->fill(['is_custom' => $custom]);
        } elseif ($own = $this->rows($type->key(), $resource->getKey(), $context)->where('path_hash', SeoUrl::hash($path))->first()) {
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

            $row = (new SeoUrl)->fill([
                'sales_channel_id' => $context->channelId,
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
     * The path, or the first alternative the collision policy offers, that is not reserved for another
     * type or used by another page's canonical URL. There is no limit to the number of alternatives:
     * one query loads every taken "{path}-N", and the first number not among them is free.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function freePath(SeoResourceType $type, Model $resource, SalesContext $context, string $path, bool $custom): string
    {
        $inContext = fn () => SeoUrl::query()->where('sales_channel_id', $context->channelId)->where('locale', $context->locale->value);
        $numbered = null;
        $tried = [];

        for ($attempt = 0; ; $attempt++) {
            $candidate = $attempt === 0 ? $path : $this->collisions->alternative($path, $attempt, $custom);

            if ($candidate === null) {
                throw InvalidSeoPath::taken($path);
            }

            if (isset($tried[$candidate])) {
                throw new LogicException('The collision policy ['.$this->collisions::class."] offered [{$candidate}] twice for [{$path}].");
            }

            $tried[$candidate] = true;

            if ($this->resources->isReservedFor($candidate, $type, $context)) {
                if ($custom) {
                    throw InvalidSeoPath::reserved($candidate);
                }

                continue;
            }

            if ($attempt > 0 && SeoUrl::withoutNumber($candidate) === $path) {
                // The numbered variants of the path, loaded once, e.g. "p/boots-2" to "p/boots-101".
                $numbered ??= $inContext()->where('base_path_hash', SeoUrl::hash($path))->get()->keyBy('path');
                $row = $numbered->get($candidate);
            } else {
                $row = $inContext()->where('path_hash', SeoUrl::hash($candidate))->first();
            }

            if ($row === null || ($row->resource_type === $type->key() && $row->resource_id === (int) $resource->getKey())) {
                return $candidate;
            }

            if ($row->status !== SeoUrlStatus::Canonical) {
                // Another page's old or gone URL: the new canonical URL claims it.
                $row->delete();
                event(SeoUrlReleased::of($row));

                return $candidate;
            }
        }
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

    /**
     * Announces the change once the transaction commits, and returns it; null when nothing changed.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function changed(SeoResourceType $type, Model $resource, SalesContext $context, ?string $old, ?string $new): ?SeoUrlChanged
    {
        if ($old === $new) {
            return null;
        }

        event($change = new SeoUrlChanged($type->key(), (int) $resource->getKey(), $context, $old, $new));

        return $change;
    }

    /**
     * @param  SeoResourceType<Model>  $type
     * @param  list<SalesContext>  $contexts
     * @param  array<string, true>  $ancestors  The pages this refresh came through, to stop at a cycle
     * @return list<SeoUrlChanged>
     */
    private function refreshTree(SeoResourceType $type, int|string $id, array $contexts, bool $cascade, array $ancestors): array
    {
        $resource = $this->lock($type, $id);

        if ($resource === null) {
            return [];
        }

        $changes = [];

        foreach ($contexts as $context) {
            if ($change = $this->refreshIn($type, $resource, $context)) {
                $changes[] = $change;
            }
        }

        if ($changes !== [] && $cascade) {
            array_push($changes, ...$this->refreshDependents($type, $resource, $contexts, $ancestors));
        }

        return $changes;
    }

    /**
     * Refreshes the pages built on this one's URL, in the same transaction, however many there are.
     *
     * @param  SeoResourceType<Model>  $type
     * @param  list<SalesContext>  $contexts
     * @param  array<string, true>  $ancestors
     * @return list<SeoUrlChanged>
     */
    private function refreshDependents(SeoResourceType $type, Model $resource, array $contexts, array $ancestors): array
    {
        $ancestors[$type->key().':'.$resource->getKey()] = true;
        $changes = [];

        foreach ($type->dependents($resource) as $dependent) {
            $dependentType = $this->resources->for($dependent);

            // The catalog keeps categories acyclic; a cycle means a write bypassed it, and refreshing would never end.
            if (isset($ancestors[$dependentType->key().':'.$dependent->getKey()])) {
                throw new LogicException('['.$dependentType->key().' '.$dependent->getKey().'] depends on itself: the pages form a cycle.');
            }

            array_push($changes, ...$this->refreshTree($dependentType, $dependent->getKey(), $contexts, true, $ancestors));
        }

        return $changes;
    }

    /**
     * The page as stored now, locked until the transaction ends, so writes for one page queue up.
     *
     * @param  SeoResourceType<Model>  $type
     */
    private function lock(SeoResourceType $type, int|string $id): ?Model
    {
        return $type->model()::query()->lockForUpdate()->find($id);
    }

    /**
     * @param  SeoResourceType<Model>  $type
     * @param  Closure(SalesContext): array<string, mixed>  $snapshot
     */
    private function gone(SeoResourceType $type, int|string $id, Closure $snapshot, ?Model $replacement): void
    {
        $replacementKey = $replacement === null ? null : $this->resources->for($replacement)->key();

        if ($replacement !== null && $replacementKey === $type->key() && $replacement->getKey() == $id) {
            throw new LogicException('A page cannot be its own replacement.');
        }

        $this->transaction(function () use ($type, $id, $snapshot, $replacement, $replacementKey): void {
            $gone = false;

            foreach ($this->contexts() as $context) {
                $rows = $this->rows($type->key(), $id, $context);
                $live = (clone $rows)->where('status', '!=', SeoUrlStatus::Gone->value);

                (clone $live)->whereNull('went_public_at')->delete();

                if ((clone $live)->exists()) {
                    $gone = $live->update([
                        'status' => SeoUrlStatus::Gone->value,
                        'gone_snapshot' => json_encode($snapshot($context), JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]) > 0 || $gone;
                }

                if ($replacement !== null) {
                    $rows->update(['replacement_type' => $replacementKey, 'replacement_id' => $replacement->getKey(), 'updated_at' => now()]);
                }
            }

            if ($gone) {
                event(new SeoResourceGone($type->key(), (int) $id));
            }
        });
    }

    /** @return Builder<SeoUrl> */
    private function rows(string $type, int|string $id, SalesContext $context): Builder
    {
        return SeoUrl::query()
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->where('sales_channel_id', $context->channelId)
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
        $connection = (new SeoUrl)->getConnection();

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

        if (preg_match("~^{$segment}(?:/{$segment})*$~u", $path) !== 1) {
            throw InvalidSeoPath::malformed($path);
        }

        return $path;
    }
}
