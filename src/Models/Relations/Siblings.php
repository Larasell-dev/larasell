<?php

namespace Larasell\Larasell\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents the sibling categories of a category, excluding the category itself.
 *
 * @template TRelatedModel of Model
 *
 * @extends HasMany<TRelatedModel, Model>
 */
class Siblings extends HasMany
{
    /**
     * @param  Builder<TRelatedModel>  $query
     */
    public function __construct(Builder $query, Model $parent)
    {
        parent::__construct($query, $parent, $parent->qualifyColumn('parent_id'), 'parent_id');
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            parent::addConstraints();

            $this->query->whereKeyNot($this->parent->getKey());
        }
    }

    /**
     * Match the eagerly loaded results to their sibling categories, excluding
     * the category itself from its own sibling list.
     *
     * @param  array<int, Model>  $models
     * @param  Collection<int, TRelatedModel>  $results
     */
    public function match(array $models, Collection $results, $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute('parent_id')][] = $result;
        }

        foreach ($models as $model) {
            $siblings = collect($dictionary[$model->getAttribute('parent_id')] ?? [])
                ->reject(fn (Model $result): bool => $result->is($model))
                ->values();

            $model->setRelation($relation, $siblings);
        }

        return $models;
    }
}
