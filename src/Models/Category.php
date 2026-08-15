<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Larasell\Larasell\Enums\Visibility;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property Visibility $status
 *
 * @method static Builder<static> root()
 */
class Category extends Model
{
    use HasFactory;

    protected $table = 'larasell_categories';

    protected $guarded = [];

    protected $casts = [
        'status' => Visibility::class,
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            $this->categoryModel(),
            'parent_id'
        );
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            $this->categoryModel(),
            'parent_id'
        );
    }

    /**
     * @return HasMany<self, $this>
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

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function root(Builder $query): Builder
    {
        return $this->onlyVisible($query)->whereNull('parent_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
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
     * @param  Builder<self>|Relation<self, self, mixed>  $query
     */
    private function onlyVisible(Builder|Relation $query): Builder|Relation
    {
        return $query->where('status', Visibility::Visible->value);
    }
}
