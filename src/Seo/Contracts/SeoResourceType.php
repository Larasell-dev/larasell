<?php

namespace Larasell\Larasell\Seo\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\SalesChannels\SalesContext;

/**
 * Tells the SEO module how to treat one kind of model that has pages, such as products.
 * Register implementations in larasell.seo.resources.
 *
 * @template TModel of Model
 */
interface SeoResourceType
{
    /**
     * The name stored with this type's URLs, such as "product". It must stay the same for as long as
     * the URLs exist, so it does not follow the model class, a morph map, or a swapped model.
     */
    public function key(): string;

    /** @return class-string<Model> */
    public function model(): string;

    /**
     * Path prefixes only this type may use, such as ["p"] for "p/boots". Other types never get
     * a path in them.
     *
     * @return list<string>
     */
    public function reservedPrefixes(): array;

    /** The automatic path below the entry point, without slashes, or null for no page in this context. */
    public function path(Model $resource, SalesContext $context): ?string;

    /** Whether the page can be shown in this context right now, e.g. it is published. */
    public function isAvailable(Model $resource, SalesContext $context): bool;

    /**
     * Resources whose paths are built from this one's. They are refreshed when its path changes.
     *
     * @return Collection<int, TModel>
     */
    public function dependents(Model $resource): Collection;

    /**
     * What is kept about the resource once it is deleted, for deciding where its URLs lead.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Model $resource, SalesContext $context): array;
}
