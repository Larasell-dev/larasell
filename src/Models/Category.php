<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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

    public const RootSlug = '__root';

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
     * @param Builder<self> $query
     */
    #[Scope]
    protected function root(Builder $query): Builder
    {
        $root = static::rootCategory();

        return $this->onlyVisible($query)
            ->where('parent_id', $root->getKey())
            ->withAttributes([
                'parent_id' => $root->getKey(),
            ], asConditions: false);
    }

    public static function rootCategory(): static
    {
        return static::query()->firstOrCreate(
            ['slug' => self::RootSlug],
            [
                'name' => 'Root',
                'parent_id' => null,
                'status' => Visibility::Hidden,
            ],
        );
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
