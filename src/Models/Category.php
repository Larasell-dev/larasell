<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Enums\Visibility;

class Category extends Model
{
    use HasFactory;

    protected $table = 'larasell_categories';

    protected $guarded = [];

    protected $casts = [
        'status' => Visibility::class,
    ];

    /**
     * @return BelongsTo<Model, self>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            $this->categoryModel(),
            'parent_id'
        );
    }

    /**
     * @return HasMany<Model, self>
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            $this->categoryModel(),
            'parent_id'
        );
    }

    /**
     * @return HasMany<Model, self>
     */
    public function descendants(): HasMany
    {
        return $this->onlyVisible($this->children())->with('descendants');
    }

    public function siblings(): Builder
    {
        return $this->onlyVisible(
            $this->newRelatedCategoryQuery()
                ->whereKeyNot($this->getKey())
                ->where('parent_id', $this->parent_id)
        );
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productModel(),
            'larasell_category_product',
            'category_id',
            'product_id'
        )->withTimestamps();
    }

    protected function categoryModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.category', self::class)
            : self::class;
    }

    protected function productModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.product', Product::class)
            : Product::class;
    }

    protected function newRelatedCategoryQuery(): Builder
    {
        return (new ($this->categoryModel()))->newQuery();
    }

    /**
     * @param Builder<self> $query
     */
    private function onlyVisible(Builder $query): Builder
    {
        return $query->where('status', Visibility::Visible->value);
    }
}
