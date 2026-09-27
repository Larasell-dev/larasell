<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Relations\Siblings;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property Translatable $slug
 * @property Translatable $name
 * @property Visibility $status
 *
 * @method static Builder<static> root()
 */
class Category extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_categories';

    protected $guarded = [];

    protected $casts = [
        'slug' => TranslatableCast::class,
        'name' => TranslatableCast::class,
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

    /**
     * @return Siblings<self>
     */
    public function siblings(): Siblings
    {
        return $this->onlyVisible(
            $this->newSiblingsRelation()
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
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

    /** @return class-string<Category> */
    protected function categoryModel(): string
    {
        return app(ModelRegistry::class)->category->class();
    }

    /** @return class-string<Product> */
    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }

    /** @return Builder<Category> */
    protected function newRelatedCategoryQuery(): Builder
    {
        return app(ModelRegistry::class)->category->query();
    }

    /** @return Siblings<Category> */
    protected function newSiblingsRelation(): Siblings
    {
        $query = $this->newRelatedCategoryQuery();

        return new Siblings($query, $this);
    }

    /**
     * @template TQuery of Builder<self>|HasMany<self, $this>|Siblings<self>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function onlyVisible(Builder|HasMany|Siblings $query): Builder|HasMany|Siblings
    {
        return $query->where('status', Visibility::Visible->value);
    }
}
